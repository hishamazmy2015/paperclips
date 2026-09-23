import { describe, expect, it, beforeEach, afterEach } from "vitest";
import fs from "node:fs/promises";
import os from "node:os";
import path from "node:path";
import { executeToolCall, resolveWorkspacePath, TASK_COMPLETE_TOOL, type GeminiLiveToolRuntime } from "./tools.js";
import { classifyGeminiLiveError } from "./parse.js";

function makeRuntime(cwd: string): GeminiLiveToolRuntime {
  return {
    cwd,
    apiBaseUrl: "http://localhost:3100",
    apiKey: null,
    runId: "run-test",
    agentId: "agent-test",
    taskId: "issue-test",
    companyId: "company-test",
    defaultShellTimeoutSec: 10,
    graceSec: 2,
    onLog: async () => {},
  };
}

describe("resolveWorkspacePath", () => {
  const cwd = path.join(os.tmpdir(), "paperclip-gemini-live-ws");
  it("resolves relative paths inside the workspace", () => {
    expect(resolveWorkspacePath(cwd, "a/b.txt")).toBe(path.join(cwd, "a/b.txt"));
  });
  it("accepts absolute paths inside the workspace", () => {
    expect(resolveWorkspacePath(cwd, path.join(cwd, "x.txt"))).toBe(path.join(cwd, "x.txt"));
  });
  it("rejects parent escapes", () => {
    expect(resolveWorkspacePath(cwd, "../outside.txt")).toBeNull();
    expect(resolveWorkspacePath(cwd, "a/../../outside.txt")).toBeNull();
  });
  it("rejects absolute paths outside the workspace", () => {
    expect(resolveWorkspacePath(cwd, "/etc/passwd")).toBeNull();
  });
  it("rejects empty and NUL paths", () => {
    expect(resolveWorkspacePath(cwd, "  ")).toBeNull();
    expect(resolveWorkspacePath(cwd, "a\0b")).toBeNull();
  });
});

describe("file tools", () => {
  let dir = "";
  beforeEach(async () => {
    dir = await fs.mkdtemp(path.join(os.tmpdir(), "paperclip-gemini-live-test-"));
  });
  afterEach(async () => {
    await fs.rm(dir, { recursive: true, force: true });
  });

  it("writes, reads, edits, and lists files", async () => {
    const runtime = makeRuntime(dir);
    const written = await executeToolCall(runtime, "write_file", { path: "notes/hello.txt", content: "hello world" });
    expect(written.ok).toBe(true);
    const read = await executeToolCall(runtime, "read_file", { path: "notes/hello.txt" });
    expect(read.ok).toBe(true);
    expect((read.result as { content: string }).content).toBe("hello world");
    const edited = await executeToolCall(runtime, "edit_file", { path: "notes/hello.txt", oldText: "world", newText: "paperclip" });
    expect(edited.ok).toBe(true);
    const listed = await executeToolCall(runtime, "list_dir", { path: "notes" });
    expect(listed.ok).toBe(true);
    expect(((listed.result as { entries: Array<{ name: string }> }).entries).map((e) => e.name)).toContain("hello.txt");
  });

  it("rejects ambiguous edits without replaceAll", async () => {
    const runtime = makeRuntime(dir);
    await executeToolCall(runtime, "write_file", { path: "dup.txt", content: "a a a" });
    const edited = await executeToolCall(runtime, "edit_file", { path: "dup.txt", oldText: "a", newText: "b" });
    expect(edited.ok).toBe(false);
  });

  it("rejects writes outside the workspace", async () => {
    const runtime = makeRuntime(dir);
    const outcome = await executeToolCall(runtime, "write_file", { path: "../escape.txt", content: "x" });
    expect(outcome.ok).toBe(false);
  });

  it("runs shell commands in the workspace", async () => {
    const runtime = makeRuntime(dir);
    const outcome = await executeToolCall(runtime, "run_shell_command", { command: "pwd" });
    expect(outcome.ok).toBe(true);
    expect(String((outcome.result as { output: string }).output)).toContain(dir);
  });

  it("rejects unknown tools", async () => {
    const runtime = makeRuntime(dir);
    const outcome = await executeToolCall(runtime, "nope_tool", {});
    expect(outcome.ok).toBe(false);
  });

  it("task_complete is reserved for the run loop", async () => {
    const runtime = makeRuntime(dir);
    const outcome = await executeToolCall(runtime, TASK_COMPLETE_TOOL, { summary: "x", status: "done" });
    expect(outcome.ok).toBe(false);
  });

  it("paperclip tools fail closed without a run token", async () => {
    const runtime = makeRuntime(dir);
    for (const name of ["paperclip_get_issue", "paperclip_list_issues", "paperclip_checkout_issue", "paperclip_comment", "paperclip_update_issue"]) {
      const outcome = await executeToolCall(runtime, name, name === "paperclip_comment" ? { body: "hi" } : {});
      expect(outcome.ok).toBe(false);
    }
  });
});

describe("classifyGeminiLiveError", () => {
  it("classifies auth failures", () => {
    expect(classifyGeminiLiveError("API key not valid. Please pass a valid API key.")).toBe("gemini_live_auth_required");
    expect(classifyGeminiLiveError("Request failed with status 401")).toBe("gemini_live_auth_required");
  });
  it("classifies unknown models without substituting", () => {
    expect(classifyGeminiLiveError("models/gemini-3.1-flash-live-preview is not found")).toBe("gemini_live_model_not_found");
  });
  it("classifies quota exhaustion", () => {
    expect(classifyGeminiLiveError("Resource exhausted: quota exceeded (429)")).toBe("gemini_live_quota_exceeded");
  });
  it("classifies network failures", () => {
    expect(classifyGeminiLiveError("fetch failed: getaddrinfo ENOTFOUND generativelanguage.googleapis.com")).toBe("gemini_live_network_unavailable");
  });
});
