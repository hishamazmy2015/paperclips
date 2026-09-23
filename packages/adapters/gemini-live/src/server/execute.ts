import fs from "node:fs/promises";
import path from "node:path";
import { GoogleGenAI, Modality, type FunctionResponse, type LiveServerMessage } from "@google/genai";
import type { AdapterExecutionContext, AdapterExecutionResult } from "@paperclipai/adapter-utils";
import {
  asNumber,
  asString,
  buildPaperclipEnv,
  ensureAbsoluteDirectory,
  joinPromptSections,
  parseObject,
  readPaperclipIssueWorkModeFromContext,
  renderPaperclipWakePrompt,
  isPaperclipRecoveryWakePayload,
  renderTemplate,
  stringifyPaperclipWakePayload,
  DEFAULT_PAPERCLIP_AGENT_PROMPT_TEMPLATE,
} from "@paperclipai/adapter-utils/server-utils";
import { DEFAULT_GEMINI_LIVE_MODEL } from "../index.js";
import {
  buildFunctionDeclarations,
  executeToolCall,
  TASK_COMPLETE_TOOL,
  type GeminiLiveToolRuntime,
} from "./tools.js";
import { classifyGeminiLiveError, describeGeminiLiveError, isRetryableConnectError } from "./parse.js";
import { MAX_VOICE_PCM_BYTES, parsePcmSampleRate, pcmToWav, DEFAULT_VOICE_SAMPLE_RATE } from "./audio.js";

const DEFAULT_TIMEOUT_SEC = 600;
const MAX_TIMEOUT_SEC = 3600;
const DEFAULT_CONNECT_TIMEOUT_SEC = 30;
const DEFAULT_MAX_TOOL_STEPS = 40;
const MAX_TOOL_STEPS_HARD_CAP = 200;
const DEFAULT_MAX_TURNS = 25;
const CONNECT_ATTEMPTS = 2;

type CompletedToolCall = { id: string; name: string; ok: boolean; resultPreview: string };

function firstNonEmptyLine(text: string): string {
  return text.split(/\r?\n/).map((line) => line.trim()).find(Boolean) ?? "";
}

function sanitizeForLog(text: string, maxChars = 2000): string {
  return text.length <= maxChars ? text : `${text.slice(0, maxChars)}…[truncated ${text.length - maxChars} chars]`;
}

function readApiKey(env: Record<string, string>): string | null {
  for (const key of ["GEMINI_API_KEY", "GOOGLE_API_KEY"]) {
    const value = env[key]?.trim();
    if (value) return value;
  }
  return null;
}

/** Resolved env for the run: Paperclip vars + adapter env bindings (secrets already resolved by the server). */
function resolveRunEnv(ctx: AdapterExecutionContext): Record<string, string> {
  const envConfig = parseObject(ctx.config.env);
  const env: Record<string, string> = { ...buildPaperclipEnv(ctx.agent) };
  for (const [key, value] of Object.entries(envConfig)) {
    if (typeof value === "string") env[key] = value;
  }
  env.PAPERCLIP_RUN_ID = ctx.runId;
  const wakeTaskId =
    (typeof ctx.context.taskId === "string" && ctx.context.taskId.trim()) ||
    (typeof ctx.context.issueId === "string" && ctx.context.issueId.trim()) ||
    null;
  if (wakeTaskId) env.PAPERCLIP_TASK_ID = wakeTaskId;
  const issueWorkMode = readPaperclipIssueWorkModeFromContext(ctx.context);
  if (issueWorkMode) env.PAPERCLIP_ISSUE_WORK_MODE = issueWorkMode;
  return env;
}

function readSessionState(runtime: AdapterExecutionContext["runtime"]): {
  handle: string | null;
  cwd: string;
  completed: CompletedToolCall[];
  toolSteps: number;
} {
  const params = parseObject(runtime.sessionParams);
  const handle = typeof params.liveSessionHandle === "string" && params.liveSessionHandle.trim() ? params.liveSessionHandle.trim() : null;
  const cwd = typeof params.cwd === "string" && params.cwd.trim() ? params.cwd : "";
  const completed = Array.isArray(params.completedToolCalls)
    ? (params.completedToolCalls as unknown[]).filter(
      (entry): entry is CompletedToolCall =>
        typeof entry === "object" && entry !== null && typeof (entry as { id?: unknown }).id === "string",
    )
    : [];
  const toolSteps = typeof params.toolStepCount === "number" && Number.isFinite(params.toolStepCount) ? Math.max(0, Math.floor(params.toolStepCount)) : 0;
  return { handle, cwd, completed, toolSteps };
}

function extractTextFromMessage(msg: LiveServerMessage): {
  text: string;
  audioBytes: number;
  audioChunks: Array<{ data: string; mimeType: string }>;
} {
  let text = "";
  let audioBytes = 0;
  const audioChunks: Array<{ data: string; mimeType: string }> = [];
  const modelTurn = msg.serverContent?.modelTurn;
  const parts = Array.isArray(modelTurn?.parts) ? modelTurn.parts : [];
  for (const part of parts) {
    const record = part as unknown as Record<string, unknown>;
    if (typeof record.text === "string") text += record.text;
    // Audio output bytes are surfaced as base64 chunks; the caller captures or discards them.
    if (typeof record.inlineData === "object" && record.inlineData !== null) {
      const inline = record.inlineData as { data?: unknown; mimeType?: unknown };
      if (typeof inline.data === "string") {
        audioBytes += Math.floor((inline.data.length * 3) / 4);
        audioChunks.push({ data: inline.data, mimeType: typeof inline.mimeType === "string" ? inline.mimeType : "" });
      }
    }
  }
  const outputTranscription = msg.serverContent?.outputTranscription;
  if (outputTranscription && typeof (outputTranscription as { text?: unknown }).text === "string") {
    text += (outputTranscription as { text: string }).text;
  }
  return { text, audioBytes, audioChunks };
}

type TurnEvent =
  | { kind: "toolCall"; calls: Array<{ id: string; name: string; args: unknown }> }
  | { kind: "turnComplete" }
  | { kind: "closed"; reason: string }
  | { kind: "deadline" };

interface LiveConnection {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  session: any;
  messages: LiveServerMessage[];
  closed: { reason: string } | null;
  setupComplete: boolean;
  close(): void;
}

async function connectLive(input: {
  apiKey: string;
  model: string;
  systemInstruction: string;
  temperature: number;
  maxOutputTokens: number;
  useAudioModality: boolean;
  voiceName: string;
  resumeHandle: string | null;
  connectTimeoutMs: number;
  onLog: AdapterExecutionContext["onLog"];
}): Promise<LiveConnection> {
  const ai = new GoogleGenAI({ apiKey: input.apiKey });
  const messages: LiveServerMessage[] = [];
  const waiters: Array<(msg: LiveServerMessage | null) => void> = [];
  const connection: LiveConnection = {
    session: null,
    messages,
    closed: null,
    setupComplete: false,
    close() {
      try {
        connection.session?.close?.();
      } catch {
        // ignore close errors
      }
    },
  };
  const push = (msg: LiveServerMessage | null) => {
    if (msg) messages.push(msg);
    const waiter = waiters.shift();
    if (waiter) waiter(msg);
  };

  // The SDK's connect() promise may never settle when the server rejects the
  // handshake (observed: open → immediate close on bad auth), so race it
  // against the connect timeout and the early-close signal. The holder object
  // (not a narrowed local) carries the close reason across the callbacks.
  const earlyClose: { reason: string | null } = { reason: null };
  const connectPromise = ai.live.connect({
    model: input.model,
    config: {
      responseModalities: input.useAudioModality ? [Modality.AUDIO] : [Modality.TEXT],
      ...(input.useAudioModality ? { outputAudioTranscription: {} } : {}),
      ...(input.useAudioModality && input.voiceName
        ? { speechConfig: { voiceConfig: { prebuiltVoiceConfig: { voiceName: input.voiceName } } } }
        : {}),
      systemInstruction: input.systemInstruction,
      temperature: input.temperature,
      ...(input.maxOutputTokens > 0 ? { maxOutputTokens: input.maxOutputTokens } : {}),
      tools: [{ functionDeclarations: buildFunctionDeclarations() }],
      ...(input.resumeHandle ? { sessionResumption: { handle: input.resumeHandle } } : { sessionResumption: {} }),
    },
    callbacks: {
      onopen: () => {
        void input.onLog("stdout", "[gemini-live] websocket open\n");
      },
      onmessage: (msg: LiveServerMessage) => {
        if (msg.setupComplete) connection.setupComplete = true;
        push(msg);
      },
      onerror: (err: unknown) => {
        const message = err instanceof Error ? err.message : String(err ?? "");
        void input.onLog("stderr", `[gemini-live] websocket error: ${sanitizeForLog(message, 500)}\n`);
      },
      onclose: (event: unknown) => {
        const record = (typeof event === "object" && event !== null ? event : {}) as Record<string, unknown>;
        const reason = firstNonEmptyLine(String(record.reason ?? record.code ?? "closed")) || "websocket closed";
        earlyClose.reason = reason;
        connection.closed = { reason };
        push(null);
        void input.onLog("stdout", `[gemini-live] websocket close: ${sanitizeForLog(reason, 300)}\n`);
      },
    },
  });

  const deadline = Date.now() + input.connectTimeoutMs;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  let session: any = null;
  let connectError: unknown = null;
  connectPromise.then(
    (value) => {
      session = value;
      connection.session = value;
    },
    (err) => {
      connectError = err;
    },
  );
  for (;;) {
    if (session || connection.setupComplete) {
      connection.session = session ?? connection.session;
      return connection;
    }
    if (connectError) throw connectError;
    if (earlyClose.reason && !session) {
      throw new Error(`Live connect closed before setup: ${earlyClose.reason}`);
    }
    if (Date.now() >= deadline) {
      try {
        (session as { close?: () => void } | null)?.close?.();
      } catch {
        // ignore
      }
      throw new Error(`Live connect timed out after ${Math.round(input.connectTimeoutMs / 1000)}s`);
    }
    await new Promise((resolve) => setTimeout(resolve, 50));
  }
}

async function nextTurnEvent(
  connection: LiveConnection,
  deadlineMs: number,
  onSideChannel: (msg: LiveServerMessage) => Promise<void>,
  onNotice: (line: string) => Promise<void>,
): Promise<TurnEvent> {
  for (;;) {
    const msg = connection.messages.shift();
    if (msg) {
      await onSideChannel(msg);
      const calls = msg.toolCall?.functionCalls;
      if (calls && calls.length > 0) {
        return {
          kind: "toolCall",
          calls: calls.map((call, index) => ({
            id: typeof call.id === "string" && call.id ? call.id : `call-${Date.now()}-${index}`,
            name: typeof call.name === "string" && call.name ? call.name : "",
            args: call.args ?? {},
          })),
        };
      }
      const cancelledIds = msg.toolCallCancellation?.ids ?? [];
      if (cancelledIds.length > 0) {
        // Synchronous calling never has tools in flight, so there is nothing
        // to undo; surface it for the transcript and continue the turn.
        await onNotice(`[gemini-live] toolCallCancellation for ${cancelledIds.length} call(s) (sync calling: nothing in flight)\n`);
      }
      if (msg.goAway) {
        // Server will disconnect soon; keep draining the current turn.
        await onNotice("[gemini-live] server signalled goAway; finishing the current step before any reconnect\n");
        continue;
      }
      if (msg.serverContent?.turnComplete) return { kind: "turnComplete" };
      continue;
    }
    if (connection.closed) return { kind: "closed", reason: connection.closed.reason };
    const remaining = deadlineMs - Date.now();
    if (remaining <= 0) return { kind: "deadline" };
    await new Promise((resolve) => setTimeout(resolve, Math.min(100, remaining)));
  }
}

function readTaskCompleteArgs(args: unknown): { summary: string; status: string; verificationNote: string } | { error: string } {
  if (typeof args !== "object" || args === null || Array.isArray(args)) return { error: "task_complete args must be an object." };
  const record = args as Record<string, unknown>;
  const summary = typeof record.summary === "string" ? record.summary.trim() : "";
  const status = typeof record.status === "string" ? record.status.trim() : "";
  if (!summary) return { error: "task_complete requires a non-empty summary." };
  if (!["done", "blocked", "needs_review"].includes(status)) {
    return { error: 'task_complete requires status of "done", "blocked", or "needs_review".' };
  }
  const verificationNote = typeof record.verificationNote === "string" ? record.verificationNote : "";
  return { summary, status, verificationNote };
}

export async function execute(ctx: AdapterExecutionContext): Promise<AdapterExecutionResult> {
  const { runId, agent, runtime, config, context, onLog, onMeta, onDispatch } = ctx;
  const startedAt = Date.now();
  const model = asString(config.model, DEFAULT_GEMINI_LIVE_MODEL).trim() || DEFAULT_GEMINI_LIVE_MODEL;
  const env = resolveRunEnv(ctx);
  const apiKey = readApiKey({ ...process.env, ...env } as Record<string, string>);
  if (!apiKey) {
    const message = "Gemini Live API key is missing. Bind GEMINI_API_KEY (or GOOGLE_API_KEY) via a company secret binding (adapterConfig.env).";
    await onLog("stderr", `[gemini-live] ${message}\n`);
    return {
      exitCode: 1,
      signal: null,
      timedOut: false,
      errorMessage: message,
      errorCode: "gemini_live_api_key_missing",
      provider: "google",
      model,
    };
  }

  const timeoutSec = Math.min(Math.max(60, asNumber(config.timeoutSec, DEFAULT_TIMEOUT_SEC)), MAX_TIMEOUT_SEC);
  const connectTimeoutSec = Math.min(Math.max(5, asNumber(config.connectTimeoutSec, DEFAULT_CONNECT_TIMEOUT_SEC)), 120);
  const graceSec = Math.max(1, asNumber(config.graceSec, 20));
  const shellTimeoutSec = Math.min(Math.max(5, asNumber(config.shellTimeoutSec, 120)), 600);
  const maxToolSteps = Math.min(Math.max(1, asNumber(config.maxToolSteps, DEFAULT_MAX_TOOL_STEPS)), MAX_TOOL_STEPS_HARD_CAP);
  const maxTurns = Math.min(Math.max(1, asNumber(config.maxTurns, DEFAULT_MAX_TURNS)), 100);
  const temperature = Math.min(Math.max(0, asNumber(config.temperature, 0.2)), 2);
  const maxOutputTokens = Math.max(0, Math.floor(asNumber(config.maxOutputTokens, 0)));
  const requestedModalities = Array.isArray(config.responseModalities)
    ? (config.responseModalities as unknown[]).filter((entry): entry is string => typeof entry === "string").map((entry) => entry.toUpperCase())
    : ["TEXT"];
  const enableVoice = config.enableVoice === true || config.enableVoice === "true";
  const voiceName = asString(config.voiceName, "").trim();
  const wantsAudio = requestedModalities.includes("AUDIO") || enableVoice;

  // Workspace: heartbeat-provided cwd wins; adapter cwd is the fallback.
  const workspaceContext = parseObject(context.paperclipWorkspace);
  const workspaceCwd = asString(workspaceContext.cwd, "");
  const configuredCwd = asString(config.cwd, "");
  const priorState = readSessionState(runtime);
  const cwd = workspaceCwd || priorState.cwd || configuredCwd || process.cwd();
  await ensureAbsoluteDirectory(cwd, { createIfMissing: true });

  // Prompt assembly mirrors the CLI adapters (instructions + wake + template).
  const promptTemplate = asString(config.promptTemplate, DEFAULT_PAPERCLIP_AGENT_PROMPT_TEMPLATE);
  const instructionsFilePath = asString(config.instructionsFilePath, "").trim();
  let instructions = "";
  if (instructionsFilePath) {
    try {
      instructions = await fs.readFile(instructionsFilePath, "utf8");
    } catch (err) {
      await onLog("stdout", `[gemini-live] Warning: could not read instructions file "${instructionsFilePath}": ${err instanceof Error ? err.message : String(err)}\n`);
    }
  }
  const templateData = {
    agentId: agent.id,
    companyId: agent.companyId,
    runId,
    company: { id: agent.companyId },
    agent,
    run: { id: runId, source: "on_demand" },
    context,
  };
  const wakePrompt = renderPaperclipWakePrompt(context.paperclipWake, { resumedSession: Boolean(priorState.handle) });
  const renderedPrompt = isPaperclipRecoveryWakePayload(context.paperclipWake) ? "" : renderTemplate(promptTemplate, templateData);
  const toolGuidance = [
    "You execute tasks through synchronous function calls. Work step by step:",
    "1) Inspect the bound task with paperclip_get_issue (and the workspace with read_file/list_dir).",
    "2) Check out the issue with paperclip_checkout_issue before starting work.",
    "3) Make workspace changes with write_file/edit_file/run_shell_command (workspace-bounded).",
    "4) Record progress with paperclip_comment and finish with paperclip_update_issue.",
    `5) End the run by calling task_complete exactly once with status done, blocked, or needs_review.`,
    `You have at most ${maxToolSteps} tool steps this run. Do not narrate instead of acting; prefer tool calls over prose.`,
  ].join("\n");
  const initialUserText = joinPromptSections([wakePrompt, stringifyPaperclipWakePayload(context.paperclipWake), toolGuidance, renderedPrompt]);
  const systemInstruction = joinPromptSections([
    instructions,
    `You are the Paperclip agent "${agent.name}" (${agent.id}) for company ${agent.companyId}.`,
    "Run credentials are injected server-side and are never visible to you. Never ask for API keys or secrets; never print environment contents.",
    "All file and shell tools are confined to the run workspace. Paperclip API tools are company-scoped to your run token.",
  ]);
  const promptMetrics = { promptChars: initialUserText.length, systemChars: systemInstruction.length };

  if (onMeta) {
    await onMeta({
      adapterType: "gemini_live",
      command: "gemini-live",
      cwd,
      commandNotes: [
        `Live API model ${model} via @google/genai (stateful WebSocket, no local process).`,
        `Synchronous function calling: ${maxToolSteps} max tool steps, ${maxTurns} max turns, ${timeoutSec}s deadline.`,
        wantsAudio
          ? enableVoice
            ? `AUDIO modality with voice capture${voiceName ? ` (voice ${voiceName})` : ""}; spoken audio saved as a workspace WAV file.`
            : "AUDIO response modality requested; output transcription enabled, audio bytes discarded."
          : "TEXT response modality.",
        priorState.handle ? "Resuming prior Live session handle when resumable." : "Starting a fresh Live session.",
      ],
      commandArgs: ["connect", model],
      env: {
        PAPERCLIP_AGENT_ID: env.PAPERCLIP_AGENT_ID ?? agent.id,
        PAPERCLIP_COMPANY_ID: env.PAPERCLIP_COMPANY_ID ?? agent.companyId,
        PAPERCLIP_RUN_ID: runId,
        ...(env.PAPERCLIP_TASK_ID ? { PAPERCLIP_TASK_ID: env.PAPERCLIP_TASK_ID } : {}),
      },
      prompt: initialUserText,
      promptMetrics,
      context,
    });
  }

  const deadlineMs = startedAt + timeoutSec * 1000;
  const toolRuntime: GeminiLiveToolRuntime = {
    cwd,
    apiBaseUrl: env.PAPERCLIP_API_URL ?? `http://localhost:${process.env.PAPERCLIP_LISTEN_PORT ?? process.env.PORT ?? "3100"}`,
    apiKey: ctx.authToken ?? null,
    runId,
    agentId: agent.id,
    taskId:
      (typeof context.taskId === "string" && context.taskId.trim()) ||
      (typeof context.issueId === "string" && context.issueId.trim()) ||
      null,
    companyId: agent.companyId,
    defaultShellTimeoutSec: shellTimeoutSec,
    graceSec,
    onLog,
  };

  const completedById = new Map<string, CompletedToolCall>();
  for (const entry of priorState.completed) completedById.set(entry.id, entry);
  let toolSteps = priorState.toolSteps;
  let turns = 0;
  let inputTokens = 0;
  let outputTokens = 0;
  let audioBytesDiscarded = 0;
  const voicePcmChunks: Buffer[] = [];
  let voicePcmBytes = 0;
  let voiceSampleRate = DEFAULT_VOICE_SAMPLE_RATE;
  let voiceTruncated = false;
  let liveHandle: string | null = null;
  let transcriptTail = "";
  let completion: { summary: string; status: string; verificationNote: string } | null = null;
  let useAudioModality = wantsAudio;
  let resumeHandle: string | null = priorState.handle;
  let lastError: string | null = null;

  const appendTranscript = (text: string) => {
    if (!text) return;
    transcriptTail = `${transcriptTail}${text}`.slice(-8000);
  };

  onDispatch?.();

  for (let attempt = 0; attempt < CONNECT_ATTEMPTS; attempt++) {
    let connection: LiveConnection | null = null;
    try {
      connection = await connectLive({
        apiKey,
        model,
        systemInstruction,
        temperature,
        maxOutputTokens,
        useAudioModality,
        voiceName,
        resumeHandle,
        connectTimeoutMs: connectTimeoutSec * 1000,
        onLog,
      });
      await onLog("stdout", `[gemini-live] connected to ${model} (${useAudioModality ? "AUDIO+transcription" : "TEXT"} modality${resumeHandle ? ", resumed" : ""})\n`);
      if (attempt > 0 || resumeHandle) resumeHandle = null;

      connection.session.sendClientContent({ turns: [{ role: "user", parts: [{ text: initialUserText }] }], turnComplete: true });

      const processSideChannel = async (msg: LiveServerMessage): Promise<void> => {
        const usage = msg.usageMetadata as unknown as Record<string, unknown> | undefined;
        if (usage) {
          if (typeof usage.promptTokenCount === "number") inputTokens = Math.max(inputTokens, usage.promptTokenCount);
          const candidates = (usage as { candidatesTokenCount?: unknown }).candidatesTokenCount;
          const responseCount = (usage as { responseTokenCount?: unknown }).responseTokenCount;
          const out = typeof candidates === "number" ? candidates : typeof responseCount === "number" ? responseCount : 0;
          if (out > 0) outputTokens = Math.max(outputTokens, out);
        }
        const update = msg.sessionResumptionUpdate;
        if (update?.resumable && update.newHandle) liveHandle = update.newHandle;
        const { text, audioBytes, audioChunks } = extractTextFromMessage(msg);
        if (enableVoice) {
          for (const chunk of audioChunks) {
            if (voicePcmBytes >= MAX_VOICE_PCM_BYTES) {
              voiceTruncated = true;
              break;
            }
            const rate = parsePcmSampleRate(chunk.mimeType);
            if (rate) voiceSampleRate = rate;
            const buf = Buffer.from(chunk.data, "base64");
            voicePcmChunks.push(buf);
            voicePcmBytes += buf.length;
          }
        } else {
          audioBytesDiscarded += audioBytes;
        }
        if (text) {
          await onLog("stdout", `[gemini-live] assistant: ${sanitizeForLog(text, 3000)}\n`);
          appendTranscript(text);
        }
      };
      const onNotice = async (line: string): Promise<void> => {
        await onLog("stdout", line);
      };

      for (;;) {
        if (Date.now() >= deadlineMs) {
          lastError = `Run deadline reached after ${timeoutSec}s`;
          break;
        }
        if (turns >= maxTurns) {
          lastError = `Max turns reached (${maxTurns}) without task_complete`;
          break;
        }
        const event = await nextTurnEvent(connection, deadlineMs, processSideChannel, onNotice);
        if (event.kind === "deadline") {
          lastError = `Run deadline reached after ${timeoutSec}s`;
          break;
        }
        if (event.kind === "closed") {
          lastError = `Live session closed: ${event.reason}`;
          break;
        }
        if (event.kind === "turnComplete") {
          turns += 1;
          // Drain any queued messages already arrived with this turn before deciding.
          continue;
        }
        // toolCall: validate, execute, respond with matching IDs (sync calling).
        const responses: FunctionResponse[] = [];
        for (const call of event.calls) {
          if (toolSteps >= maxToolSteps) {
            responses.push({ id: call.id, name: call.name, response: { result: `Tool step budget exhausted (${maxToolSteps}). Call task_complete with the current outcome.` } });
            continue;
          }
          if (!call.name) {
            responses.push({ id: call.id, name: "unknown", response: { error: "Function call missing a name." } });
            continue;
          }
          if (call.name === TASK_COMPLETE_TOOL) {
            const parsed = readTaskCompleteArgs(call.args);
            if ("error" in parsed) {
              responses.push({ id: call.id, name: call.name, response: { error: parsed.error } });
              continue;
            }
            completion = parsed;
            responses.push({ id: call.id, name: call.name, response: { result: "Recorded. Ending the run." } });
            await onLog("stdout", `[gemini-live] task_complete status=${parsed.status}\n${sanitizeForLog(parsed.summary, 1200)}\n`);
            continue;
          }
          const cached = completedById.get(call.id);
          if (cached) {
            responses.push({ id: call.id, name: call.name, response: { result: `(cached, not re-executed) ${cached.resultPreview}` } });
            continue;
          }
          toolSteps += 1;
          await onLog("stdout", `[gemini-live] tool_call ${call.name} (${toolSteps}/${maxToolSteps})\n`);
          const outcome = await executeToolCall(toolRuntime, call.name, call.args);
          const preview = sanitizeForLog(JSON.stringify(outcome.result), 600);
          completedById.set(call.id, { id: call.id, name: call.name, ok: outcome.ok, resultPreview: preview });
          await onLog("stdout", `[gemini-live] tool_result ${call.name} ok=${outcome.ok}\n${sanitizeForLog(JSON.stringify(outcome.result), 1500)}\n`);
          responses.push({ id: call.id, name: call.name, response: { result: outcome.result } });
        }
        connection.session.sendToolResponse({ functionResponses: responses });
        if (completion) break;
      }

      // The turn pump already processed every queued message (text, usage,
      // resumption handles) through processSideChannel above.
      connection.close();
      break;
    } catch (err) {
      const raw = err instanceof Error ? err.message : String(err);
      const firstLine = firstNonEmptyLine(raw) || "unknown error";
      connection?.close();
      // TEXT→AUDIO fallback when the model requires audio output.
      if (!useAudioModality && /modality|response_modalities|AUDIO/i.test(raw) && attempt === 0) {
        useAudioModality = true;
        await onLog("stdout", "[gemini-live] TEXT modality rejected; retrying with AUDIO + output transcription (audio bytes discarded).\n");
        continue;
      }
      const code = /timed out after \d+s$/.test(firstLine) && firstLine.startsWith("Live connect timed out")
        ? "gemini_live_connect_timeout"
        : classifyGeminiLiveError(raw);
      lastError = describeGeminiLiveError(code, firstLine);
      if (isRetryableConnectError(code) && attempt + 1 < CONNECT_ATTEMPTS) {
        await onLog("stdout", `[gemini-live] retryable connect error (${code}); retrying once.\n`);
        await new Promise((resolve) => setTimeout(resolve, 2000));
        continue;
      }
      await onLog("stderr", `[gemini-live] ${lastError}\n`);
      return {
        exitCode: 1,
        signal: null,
        timedOut: code === "gemini_live_connect_timeout",
        errorMessage: lastError,
        errorCode: code,
        provider: "google",
        model,
        sessionParams: {
          ...(resumeHandle || liveHandle ? { liveSessionHandle: liveHandle ?? resumeHandle } : {}),
          cwd,
          completedToolCalls: [...completedById.values()].slice(-200),
          toolStepCount: toolSteps,
        },
      };
    }
  }

  // Captured voice audio is written even when the run ends without task_complete:
  // partial speech is still evidence, and the file lands in the run workspace
  // where the workspace browser can serve it.
  let voiceFilePath: string | null = null;
  if (enableVoice && voicePcmBytes > 0) {
    try {
      const wav = pcmToWav(Buffer.concat(voicePcmChunks), voiceSampleRate);
      const target = path.join(cwd, `gemini-voice-${runId}.wav`);
      await fs.writeFile(target, wav);
      voiceFilePath = target;
      await onLog(
        "stdout",
        `[gemini-live] voice audio saved: ${target} (${wav.length} bytes, ${voiceSampleRate} Hz${voiceTruncated ? ", truncated at capture cap" : ""})\n`,
      );
    } catch (err) {
      await onLog("stderr", `[gemini-live] failed to save voice audio: ${err instanceof Error ? err.message : String(err)}\n`);
    }
  }

  // turnComplete without task_complete leaves the issue open: the transcript
  // carries the partial result and the next heartbeat can continue.
  if (lastError && !completion && transcriptTail.trim().length === 0 && toolSteps === 0) {
    const code = /deadline|timed out/i.test(lastError) ? "gemini_live_timeout" : classifyGeminiLiveError(lastError);
    return {
      exitCode: 1,
      signal: null,
      timedOut: code === "gemini_live_timeout",
      errorMessage: lastError,
      errorCode: code,
      provider: "google",
      model,
      ...(inputTokens > 0 || outputTokens > 0 ? { usage: { inputTokens, outputTokens } } : {}),
      sessionParams: {
        ...(liveHandle ? { liveSessionHandle: liveHandle } : {}),
        cwd,
        completedToolCalls: [...completedById.values()].slice(-200),
        toolStepCount: toolSteps,
      },
    };
  }

  // Session-expiry style disconnects without completion: mark clearSession so
  // the next heartbeat starts fresh instead of resuming a dead handle.
  const closedWithoutCompletion = !completion && lastError !== null;
  const summary = completion
    ? `[${completion.status}] ${completion.summary}${completion.verificationNote ? `\nVerification: ${completion.verificationNote}` : ""}`
    : [
      transcriptTail.trim() || "Model finished without calling task_complete; issue left open with this transcript as evidence.",
      lastError ? `Note: ${lastError}` : "",
    ].filter(Boolean).join("\n");

  await onLog("stdout", `[gemini-live] run finished turns=${turns} toolSteps=${toolSteps} audioBytesDiscarded=${audioBytesDiscarded} voiceBytesCaptured=${voicePcmBytes} completion=${completion ? completion.status : "none"}\n`);

  return {
    exitCode: 0,
    signal: null,
    timedOut: false,
    provider: "google",
    biller: "google",
    model,
    billingType: "api",
    // No costUsd: the Live API returns usage, not pricing — cost is unknown, not zero.
    ...(inputTokens > 0 || outputTokens > 0 ? { usage: { inputTokens, outputTokens } } : {}),
    sessionId: liveHandle,
    sessionParams: {
      ...(liveHandle ? { liveSessionHandle: liveHandle } : {}),
      cwd,
      completedToolCalls: [...completedById.values()].slice(-200),
      toolStepCount: toolSteps,
      transcriptTail: transcriptTail.slice(-4000),
    },
    sessionDisplayId: liveHandle,
    resultJson: {
      taskCompleted: Boolean(completion),
      completionStatus: completion?.status ?? null,
      turns,
      toolSteps,
      audioBytesDiscarded,
      ...(voiceFilePath ? { voiceFilePath, voiceBytesCaptured: voicePcmBytes, voiceSampleRate, voiceTruncated } : {}),
      transcriptTail: transcriptTail.slice(-4000),
      ...(lastError ? { note: lastError } : {}),
    },
    summary: voiceFilePath ? `${summary}\nVoice audio: ${voiceFilePath}` : summary,
    clearSession: closedWithoutCompletion && /closed|expired|resum/i.test(lastError ?? ""),
  };
}
