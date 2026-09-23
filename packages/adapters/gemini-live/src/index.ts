export const type = "gemini_live";
export const label = "Gemini Live";

/** Exact Live-API model for task execution. Never silently substitute another model. */
export const DEFAULT_GEMINI_LIVE_MODEL = "gemini-3.1-flash-live-preview";

export const models = [
  { id: DEFAULT_GEMINI_LIVE_MODEL, label: "Gemini 3.1 Flash Live Preview" },
];

export const agentConfigurationDoc = `# gemini_live agent configuration

Adapter: gemini_live

Use when:
- You want Paperclip to execute tasks through the Gemini Live API
  (stateful WebSocket, model gemini-3.1-flash-live-preview) instead of a local CLI
- You want server-side tool execution (workspace files, shell, Paperclip task API)
  driven by the model's synchronous function calls
- You need session resumption across heartbeats via the Live session handle

Don't use when:
- You want the local Gemini CLI harness with its own tool loop (use gemini_local)
- You only need a one-shot script without an AI loop (use process)
- No GEMINI_API_KEY is bound to the agent (the adapter fails closed without one)

Core fields:
- model (string, optional): Live model id. Defaults to gemini-3.1-flash-live-preview.
  The adapter never substitutes another model; an unavailable model is a run error.
- cwd (string, optional): default absolute working directory fallback for file/shell
  tools (created if missing when possible). The heartbeat workspace cwd wins when set.
- instructionsFilePath (string, optional): absolute path to a markdown instructions
  file sent as the Live session system instruction.
- promptTemplate (string, optional): run prompt template.
- env (object, optional): KEY=VALUE environment variables. GEMINI_API_KEY (or
  GOOGLE_API_KEY) must resolve here via a company secret binding. Plaintext keys
  in adapter config are redacted from run metadata and logs.
- temperature (number, optional): sampling temperature, defaults to 0.2 for
  task-execution determinism.
- maxOutputTokens (number, optional): cap on generated tokens per turn.
- maxToolSteps (number, optional): maximum function-call executions per run
  (default 40, max 200). Bounds cost and prevents tool loops.
- maxTurns (number, optional): maximum model turns per run (default 25).
- enableVoice (boolean, optional): request AUDIO responses and save the spoken
  audio as gemini-voice-<runId>.wav in the run workspace (playable 16-bit PCM
  WAV, capped at 64 MB of PCM per run). Output transcription still provides the
  text transcript. Defaults to false.
- voiceName (string, optional): prebuilt Live voice (Puck, Charon, Kore,
  Fenrir, Aoede, Leda, Orus, Zephyr). Empty uses the model default voice.
  Only applies when AUDIO modality is active.
- responseModalities (string[], optional): defaults to ["TEXT"]. If the model
  requires AUDIO, the adapter falls back to AUDIO with output transcription and
  logs the fallback; raw audio bytes are discarded incrementally unless
  enableVoice is set, in which case they are captured to the workspace WAV.
- resumeHandle (managed): Live session resumption handle persisted in
  runtime.sessionParams by the adapter; do not set manually.

Operational fields:
- timeoutSec (number, optional): run timeout in seconds (default 600, max 3600).
  The Live session is closed when the deadline is reached.
- graceSec (number, optional): shell-tool SIGTERM grace period in seconds.
- connectTimeoutSec (number, optional): Live WebSocket connect timeout
  (default 30). A close before setup completion surfaces as an auth/network error.
- shellTimeoutSec (number, optional): default timeout for run_shell_command
  (default 120).

Notes:
- Gemini 3.1 Flash Live supports synchronous function calling only; the adapter
  executes each tool call to completion before responding, with matching call IDs.
- Model turn completion is not task completion: the run ends successfully only
  when the model calls task_complete (or the step/turn budget is reached, in
  which case the transcript carries the partial result and the issue stays open).
- Usage tokens are reported when the Live API returns usageMetadata. Cost is
  reported as unknown (no costUsd) because the Live API does not return pricing.
- Run credentials (GEMINI_API_KEY, PAPERCLIP_API_KEY) are scoped to the run and
  never appear in prompts, metadata, or logs.
`;
