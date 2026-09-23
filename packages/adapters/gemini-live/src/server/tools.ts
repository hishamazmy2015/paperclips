import fs from "node:fs/promises";
import path from "node:path";
import { Type, type FunctionDeclaration } from "@google/genai";
import { runChildProcess } from "@paperclipai/adapter-utils/server-utils";
import type { AdapterExecutionContext } from "@paperclipai/adapter-utils";

export interface GeminiLiveToolRuntime {
  cwd: string;
  apiBaseUrl: string;
  apiKey: string | null;
  runId: string;
  agentId: string;
  taskId: string | null;
  companyId: string;
  defaultShellTimeoutSec: number;
  graceSec: number;
  onLog: AdapterExecutionContext["onLog"];
}

export interface GeminiLiveToolOutcome {
  ok: boolean;
  /** JSON-serializable payload returned to the model as the function result. */
  result: Record<string, unknown>;
}

function asRecord(value: unknown): Record<string, unknown> {
  if (typeof value === "object" && value !== null && !Array.isArray(value)) {
    return value as Record<string, unknown>;
  }
  return {};
}

function readStringArg(args: Record<string, unknown>, key: string): string | null {
  const value = args[key];
  return typeof value === "string" && value.trim().length > 0 ? value : null;
}

function readNumberArg(args: Record<string, unknown>, key: string): number | null {
  const value = args[key];
  if (typeof value === "number" && Number.isFinite(value)) return value;
  if (typeof value === "string" && value.trim().length > 0) {
    const parsed = Number(value);
    if (Number.isFinite(parsed)) return parsed;
  }
  return null;
}

/**
 * Resolve a model-supplied path against the workspace cwd. Returns null when
 * the path escapes the workspace boundary.
 */
export function resolveWorkspacePath(cwd: string, rawPath: string): string | null {
  const trimmed = rawPath.trim();
  if (trimmed.length === 0) return null;
  // Refuse NUL bytes and parent-escape tricks up front.
  if (trimmed.includes("\0")) return null;
  const absolute = path.isAbsolute(trimmed) ? path.normalize(trimmed) : path.normalize(path.join(cwd, trimmed));
  const root = path.normalize(cwd);
  if (absolute !== root && !absolute.startsWith(root + path.sep)) return null;
  return absolute;
}

const MAX_READ_BYTES = 256 * 1024;
const MAX_OUTPUT_CHARS = 24_000;

function truncateText(text: string, maxChars = MAX_OUTPUT_CHARS): { text: string; truncated: boolean } {
  if (text.length <= maxChars) return { text, truncated: false };
  return { text: `${text.slice(0, maxChars)}\n…[truncated ${text.length - maxChars} chars]`, truncated: true };
}

async function paperclipRequest(
  runtime: GeminiLiveToolRuntime,
  method: "GET" | "POST" | "PATCH",
  urlPath: string,
  body?: Record<string, unknown>,
): Promise<{ status: number; payload: unknown }> {
  if (!runtime.apiKey) {
    throw new Error("Paperclip API unavailable for this run (no run token).");
  }
  const url = `${runtime.apiBaseUrl.replace(/\/+$/, "")}${urlPath}`;
  const headers: Record<string, string> = {
    Authorization: `Bearer ${runtime.apiKey}`,
    "X-Paperclip-Run-Id": runtime.runId,
  };
  if (body !== undefined) headers["Content-Type"] = "application/json";
  const res = await fetch(url, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  let payload: unknown = text;
  try {
    payload = text.length > 0 ? JSON.parse(text) : null;
  } catch {
    payload = text;
  }
  return { status: res.status, payload };
}

function toErrorResult(message: string, extra?: Record<string, unknown>): GeminiLiveToolOutcome {
  return { ok: false, result: { error: message, ...(extra ?? {}) } };
}

async function execReadFile(runtime: GeminiLiveToolRuntime, args: Record<string, unknown>): Promise<GeminiLiveToolOutcome> {
  const rawPath = readStringArg(args, "path");
  if (!rawPath) return toErrorResult("Missing required argument: path.");
  const resolved = resolveWorkspacePath(runtime.cwd, rawPath);
  if (!resolved) return toErrorResult(`Path escapes the workspace boundary: ${rawPath}`);
  const maxBytes = Math.min(Math.max(1, Math.floor(readNumberArg(args, "maxBytes") ?? MAX_READ_BYTES)), 1024 * 1024);
  try {
    const stat = await fs.stat(resolved);
    if (!stat.isFile()) return toErrorResult(`Not a regular file: ${rawPath}`);
    if (stat.size > maxBytes) {
      const handle = await fs.open(resolved, "r");
      try {
        const buffer = Buffer.alloc(maxBytes);
        const { bytesRead } = await handle.read(buffer, 0, maxBytes, 0);
        return {
          ok: true,
          result: {
            path: rawPath,
            byteSize: stat.size,
            truncated: true,
            content: buffer.subarray(0, bytesRead).toString("utf8"),
          },
        };
      } finally {
        await handle.close();
      }
    }
    const content = await fs.readFile(resolved, "utf8");
    const { text, truncated } = truncateText(content);
    return { ok: true, result: { path: rawPath, byteSize: stat.size, truncated, content: text } };
  } catch (err) {
    return toErrorResult(`Cannot read ${rawPath}: ${err instanceof Error ? err.message : String(err)}`);
  }
}

async function execListDir(runtime: GeminiLiveToolRuntime, args: Record<string, unknown>): Promise<GeminiLiveToolOutcome> {
  const rawPath = readStringArg(args, "path") ?? ".";
  const resolved = resolveWorkspacePath(runtime.cwd, rawPath);
  if (!resolved) return toErrorResult(`Path escapes the workspace boundary: ${rawPath}`);
  try {
    const entries = await fs.readdir(resolved, { withFileTypes: true });
    const items = entries.slice(0, 500).map((entry) => ({
      name: entry.name,
      kind: entry.isDirectory() ? "dir" : entry.isFile() ? "file" : entry.isSymbolicLink() ? "symlink" : "other",
    }));
    return { ok: true, result: { path: rawPath, entries: items, truncated: entries.length > items.length } };
  } catch (err) {
    return toErrorResult(`Cannot list ${rawPath}: ${err instanceof Error ? err.message : String(err)}`);
  }
}

async function execWriteFile(runtime: GeminiLiveToolRuntime, args: Record<string, unknown>): Promise<GeminiLiveToolOutcome> {
  const rawPath = readStringArg(args, "path");
  const content = typeof args.content === "string" ? args.content : null;
  if (!rawPath) return toErrorResult("Missing required argument: path.");
  if (content === null) return toErrorResult("Missing required argument: content (string).");
  if (content.length > 1024 * 1024) return toErrorResult("content exceeds the 1 MiB per-write limit.");
  const resolved = resolveWorkspacePath(runtime.cwd, rawPath);
  if (!resolved) return toErrorResult(`Path escapes the workspace boundary: ${rawPath}`);
  try {
    await fs.mkdir(path.dirname(resolved), { recursive: true });
    await fs.writeFile(resolved, content, "utf8");
    await runtime.onLog("stdout", `[gemini-live] write_file ${rawPath} (${content.length} chars)\n`);
    return { ok: true, result: { path: rawPath, bytesWritten: Buffer.byteLength(content, "utf8") } };
  } catch (err) {
    return toErrorResult(`Cannot write ${rawPath}: ${err instanceof Error ? err.message : String(err)}`);
  }
}

async function execEditFile(runtime: GeminiLiveToolRuntime, args: Record<string, unknown>): Promise<GeminiLiveToolOutcome> {
  const rawPath = readStringArg(args, "path");
  const oldText = typeof args.oldText === "string" ? args.oldText : null;
  const newText = typeof args.newText === "string" ? args.newText : null;
  if (!rawPath) return toErrorResult("Missing required argument: path.");
  if (oldText === null || oldText.length === 0) return toErrorResult("Missing required argument: oldText (non-empty).");
  if (newText === null) return toErrorResult("Missing required argument: newText (string).");
  const resolved = resolveWorkspacePath(runtime.cwd, rawPath);
  if (!resolved) return toErrorResult(`Path escapes the workspace boundary: ${rawPath}`);
  const replaceAll = args.replaceAll === true;
  try {
    const current = await fs.readFile(resolved, "utf8");
    const occurrences = current.split(oldText).length - 1;
    if (occurrences === 0) return toErrorResult("oldText not found in file; no changes made.", { path: rawPath });
    if (occurrences > 1 && !replaceAll) {
      return toErrorResult(`oldText matches ${occurrences} locations; set replaceAll=true or narrow oldText. No changes made.`, { path: rawPath, matches: occurrences });
    }
    const next = replaceAll ? current.split(oldText).join(newText) : current.replace(oldText, newText);
    await fs.writeFile(resolved, next, "utf8");
    await runtime.onLog("stdout", `[gemini-live] edit_file ${rawPath} (${occurrences} replacement${occurrences === 1 ? "" : "s"})\n`);
    return { ok: true, result: { path: rawPath, replacements: replaceAll ? occurrences : 1 } };
  } catch (err) {
    return toErrorResult(`Cannot edit ${rawPath}: ${err instanceof Error ? err.message : String(err)}`);
  }
}

async function execShell(runtime: GeminiLiveToolRuntime, args: Record<string, unknown>): Promise<GeminiLiveToolOutcome> {
  const command = readStringArg(args, "command");
  if (!command) return toErrorResult("Missing required argument: command.");
  if (command.length > 8000) return toErrorResult("command exceeds the 8000-character limit.");
  const timeoutSec = Math.min(Math.max(1, Math.floor(readNumberArg(args, "timeoutSec") ?? runtime.defaultShellTimeoutSec)), 600);
  await runtime.onLog("stdout", `[gemini-live] run_shell_command: ${command.slice(0, 300)}${command.length > 300 ? "…" : ""}\n`);
  try {
    const proc = await runChildProcess(runtime.runId, "/bin/sh", ["-c", command], {
      cwd: runtime.cwd,
      env: { ...process.env } as Record<string, string>,
      timeoutSec,
      graceSec: runtime.graceSec,
      onLog: runtime.onLog,
    });
    const combined = truncateText(`$ ${command}\n${proc.stdout}${proc.stderr}`.trim());
    return {
      ok: (proc.exitCode ?? 1) === 0,
      result: {
        exitCode: proc.exitCode,
        signal: proc.signal,
        timedOut: proc.timedOut,
        ...(proc.timedOut ? { error: `Timed out after ${timeoutSec}s` } : {}),
        output: combined.text,
        outputTruncated: combined.truncated,
      },
    };
  } catch (err) {
    return toErrorResult(`Shell execution failed: ${err instanceof Error ? err.message : String(err)}`);
  }
}

async function execPaperclipGetIssue(runtime: GeminiLiveToolRuntime, args: Record<string, unknown>): Promise<GeminiLiveToolOutcome> {
  const issueId = readStringArg(args, "issueId") ?? runtime.taskId;
  if (!issueId) return toErrorResult("Missing issueId and no task bound to this run.");
  try {
    const { status, payload } = await paperclipRequest(runtime, "GET", `/api/issues/${encodeURIComponent(issueId)}`);
    if (status >= 400) return toErrorResult(`Paperclip GET issue failed (HTTP ${status}).`, { response: payload });
    return { ok: true, result: { issue: payload } };
  } catch (err) {
    return toErrorResult(`Paperclip GET issue failed: ${err instanceof Error ? err.message : String(err)}`);
  }
}

async function execPaperclipListIssues(runtime: GeminiLiveToolRuntime, args: Record<string, unknown>): Promise<GeminiLiveToolOutcome> {
  const status = readStringArg(args, "status") ?? "todo,in_progress,in_review,blocked";
  const limit = Math.min(Math.max(1, Math.floor(readNumberArg(args, "limit") ?? 20)), 100);
  try {
    const query = new URLSearchParams({
      assigneeAgentId: runtime.agentId,
      status,
      limit: String(limit),
    });
    const { status: httpStatus, payload } = await paperclipRequest(
      runtime,
      "GET",
      `/api/companies/${encodeURIComponent(runtime.companyId)}/issues?${query.toString()}`,
    );
    if (httpStatus >= 400) return toErrorResult(`Paperclip list issues failed (HTTP ${httpStatus}).`, { response: payload });
    return { ok: true, result: { issues: payload } };
  } catch (err) {
    return toErrorResult(`Paperclip list issues failed: ${err instanceof Error ? err.message : String(err)}`);
  }
}

async function execPaperclipCheckout(runtime: GeminiLiveToolRuntime, args: Record<string, unknown>): Promise<GeminiLiveToolOutcome> {
  const issueId = readStringArg(args, "issueId") ?? runtime.taskId;
  if (!issueId) return toErrorResult("Missing issueId and no task bound to this run.");
  try {
    const { status, payload } = await paperclipRequest(runtime, "POST", `/api/issues/${encodeURIComponent(issueId)}/checkout`, {
      agentId: runtime.agentId,
      expectedStatuses: ["todo", "backlog", "blocked", "in_review"],
    });
    if (status >= 400) return toErrorResult(`Paperclip checkout failed (HTTP ${status}).`, { response: payload });
    return { ok: true, result: { checkout: payload } };
  } catch (err) {
    return toErrorResult(`Paperclip checkout failed: ${err instanceof Error ? err.message : String(err)}`);
  }
}

async function execPaperclipComment(runtime: GeminiLiveToolRuntime, args: Record<string, unknown>): Promise<GeminiLiveToolOutcome> {
  const issueId = readStringArg(args, "issueId") ?? runtime.taskId;
  const body = readStringArg(args, "body");
  if (!issueId) return toErrorResult("Missing issueId and no task bound to this run.");
  if (!body) return toErrorResult("Missing required argument: body.");
  if (body.length > 60_000) return toErrorResult("body exceeds the 60000-character limit.");
  try {
    const { status, payload } = await paperclipRequest(runtime, "POST", `/api/issues/${encodeURIComponent(issueId)}/comments`, { body });
    if (status >= 400) return toErrorResult(`Paperclip comment failed (HTTP ${status}).`, { response: payload });
    return { ok: true, result: { comment: payload } };
  } catch (err) {
    return toErrorResult(`Paperclip comment failed: ${err instanceof Error ? err.message : String(err)}`);
  }
}

const ALLOWED_ISSUE_STATUSES = new Set(["in_progress", "in_review", "blocked", "done"]);

async function execPaperclipUpdateIssue(runtime: GeminiLiveToolRuntime, args: Record<string, unknown>): Promise<GeminiLiveToolOutcome> {
  const issueId = readStringArg(args, "issueId") ?? runtime.taskId;
  if (!issueId) return toErrorResult("Missing issueId and no task bound to this run.");
  const status = readStringArg(args, "status");
  const comment = typeof args.comment === "string" && args.comment.trim().length > 0 ? args.comment : undefined;
  if (status && !ALLOWED_ISSUE_STATUSES.has(status)) {
    return toErrorResult(`Unsupported status "${status}". Allowed: in_progress, in_review, blocked, done.`);
  }
  if (!status && !comment) return toErrorResult("Provide at least one of: status, comment.");
  const body: Record<string, unknown> = {};
  if (status) body.status = status;
  if (comment) body.comment = comment;
  try {
    const { status: httpStatus, payload } = await paperclipRequest(runtime, "PATCH", `/api/issues/${encodeURIComponent(issueId)}`, body);
    if (httpStatus >= 400) return toErrorResult(`Paperclip update failed (HTTP ${httpStatus}).`, { response: payload });
    return { ok: true, result: { issue: payload } };
  } catch (err) {
    return toErrorResult(`Paperclip update failed: ${err instanceof Error ? err.message : String(err)}`);
  }
}

export type ToolExecutor = (runtime: GeminiLiveToolRuntime, args: Record<string, unknown>) => Promise<GeminiLiveToolOutcome>;

const EXECUTORS: Record<string, ToolExecutor> = {
  read_file: execReadFile,
  list_dir: execListDir,
  write_file: execWriteFile,
  edit_file: execEditFile,
  run_shell_command: execShell,
  paperclip_get_issue: execPaperclipGetIssue,
  paperclip_list_issues: execPaperclipListIssues,
  paperclip_checkout_issue: execPaperclipCheckout,
  paperclip_comment: execPaperclipComment,
  paperclip_update_issue: execPaperclipUpdateIssue,
};

/** task_complete is handled by the run loop itself, not by an executor. */
export const TASK_COMPLETE_TOOL = "task_complete";

function strProp(description: string, extra?: Record<string, unknown>): Record<string, unknown> {
  return { type: Type.STRING, description, ...(extra ?? {}) };
}

export function buildFunctionDeclarations(): FunctionDeclaration[] {
  return [
    {
      name: "read_file",
      description: "Read a UTF-8 text file inside the workspace. Paths are resolved against the workspace root; paths escaping it are rejected.",
      parameters: {
        type: Type.OBJECT,
        properties: { path: strProp("Workspace-relative or absolute path to read."), maxBytes: { type: Type.INTEGER, description: "Maximum bytes to read (default 262144, max 1048576)." } },
        required: ["path"],
      },
    },
    {
      name: "list_dir",
      description: "List directory entries inside the workspace.",
      parameters: {
        type: Type.OBJECT,
        properties: { path: strProp("Workspace-relative or absolute directory path (default '.').") },
      },
    },
    {
      name: "write_file",
      description: "Create or overwrite a UTF-8 text file inside the workspace (parent dirs created). Max 1 MiB per write.",
      parameters: {
        type: Type.OBJECT,
        properties: { path: strProp("Workspace-relative or absolute path to write."), content: strProp("Full file content.") },
        required: ["path", "content"],
      },
    },
    {
      name: "edit_file",
      description: "Replace exact text in a workspace file. Fails when oldText is absent or ambiguous unless replaceAll is true.",
      parameters: {
        type: Type.OBJECT,
        properties: {
          path: strProp("Workspace-relative or absolute path to edit."),
          oldText: strProp("Exact existing text to replace (non-empty)."),
          newText: strProp("Replacement text."),
          replaceAll: { type: Type.BOOLEAN, description: "Replace all occurrences (default false)." },
        },
        required: ["path", "oldText", "newText"],
      },
    },
    {
      name: "run_shell_command",
      description: "Run a non-interactive shell command in the workspace directory with a timeout. Use for inspection, tests, builds. No sudo, no background daemons.",
      parameters: {
        type: Type.OBJECT,
        properties: { command: strProp("Shell command to run."), timeoutSec: { type: Type.INTEGER, description: "Timeout in seconds (default 120, max 600)." } },
        required: ["command"],
      },
    },
    {
      name: "paperclip_get_issue",
      description: "Fetch the full Paperclip issue (task) record, including status, description, and comments context.",
      parameters: {
        type: Type.OBJECT,
        properties: { issueId: strProp("Issue id. Defaults to the run's bound task.") },
      },
    },
    {
      name: "paperclip_list_issues",
      description: "List issues assigned to this agent in the same company.",
      parameters: {
        type: Type.OBJECT,
        properties: {
          status: strProp("Comma-separated statuses (default todo,in_progress,in_review,blocked)."),
          limit: { type: Type.INTEGER, description: "Max issues (default 20, max 100)." },
        },
      },
    },
    {
      name: "paperclip_checkout_issue",
      description: "Atomically check out an issue for this agent (required before moving todo -> in_progress).",
      parameters: {
        type: Type.OBJECT,
        properties: { issueId: strProp("Issue id. Defaults to the run's bound task.") },
      },
    },
    {
      name: "paperclip_comment",
      description: "Post a comment on a Paperclip issue in the same company.",
      parameters: {
        type: Type.OBJECT,
        properties: { issueId: strProp("Issue id. Defaults to the run's bound task."), body: strProp("Markdown comment body.") },
        required: ["body"],
      },
    },
    {
      name: "paperclip_update_issue",
      description: "Update a Paperclip issue status and/or leave a comment. Allowed statuses: in_progress, in_review, blocked, done. Terminal transitions are validated server-side.",
      parameters: {
        type: Type.OBJECT,
        properties: {
          issueId: strProp("Issue id. Defaults to the run's bound task."),
          status: strProp("New status.", { enum: ["in_progress", "in_review", "blocked", "done"] }),
          comment: strProp("Comment explaining the change."),
        },
      },
    },
    {
      name: TASK_COMPLETE_TOOL,
      description: "End the run and report the final outcome. Call exactly once when the task is done, blocked, or needs review. Model turn completion without this call leaves the issue open with the transcript as evidence.",
      parameters: {
        type: Type.OBJECT,
        properties: {
          summary: strProp("What was done, what changed, and why."),
          status: strProp("Final task disposition.", { enum: ["done", "blocked", "needs_review"] }),
          verificationNote: strProp("How the result was verified (commands run, files checked)."),
        },
        required: ["summary", "status"],
      },
    },
  ];
}

/**
 * Execute one validated function call. Unknown tools and task_complete are
 * reported back so the run loop can handle them; everything else runs here.
 */
export async function executeToolCall(
  runtime: GeminiLiveToolRuntime,
  name: string,
  rawArgs: unknown,
): Promise<GeminiLiveToolOutcome> {
  const args = asRecord(rawArgs);
  const executor = EXECUTORS[name];
  if (!executor) {
    return toErrorResult(`Unknown tool "${name}". Available: ${[...Object.keys(EXECUTORS), TASK_COMPLETE_TOOL].join(", ")}.`);
  }
  return executor(runtime, args);
}
