import type { AdapterConfigSchema } from "@paperclipai/adapter-utils";
import { DEFAULT_GEMINI_LIVE_MODEL } from "../index.js";

export function getConfigSchema(): AdapterConfigSchema {
  return {
    fields: [
      {
        key: "model",
        label: "Live model",
        type: "text",
        default: DEFAULT_GEMINI_LIVE_MODEL,
        hint: "Qualified model is gemini-3.1-flash-live-preview. Overrides are untested; the adapter never substitutes silently.",
      },
      {
        key: "enableVoice",
        label: "Generate voice audio",
        type: "toggle",
        default: false,
        hint: "Request AUDIO responses and save the spoken audio as gemini-voice-<runId>.wav in the run workspace (16-bit PCM WAV). The text transcript still comes from output transcription.",
      },
      {
        key: "voiceName",
        label: "Voice",
        type: "select",
        default: "",
        options: [
          { label: "Model default", value: "" },
          { label: "Puck", value: "Puck" },
          { label: "Charon", value: "Charon" },
          { label: "Kore", value: "Kore" },
          { label: "Fenrir", value: "Fenrir" },
          { label: "Aoede", value: "Aoede" },
          { label: "Leda", value: "Leda" },
          { label: "Orus", value: "Orus" },
          { label: "Zephyr", value: "Zephyr" },
        ],
        hint: "Prebuilt Gemini Live voice used when voice generation is enabled.",
      },
      {
        key: "temperature",
        label: "Temperature",
        type: "number",
        default: 0.2,
        hint: "Sampling temperature (0-2). Lower values are more deterministic for task execution.",
      },
      {
        key: "maxOutputTokens",
        label: "Max output tokens",
        type: "number",
        default: 0,
        hint: "Cap on generated tokens per turn. 0 means no explicit cap.",
      },
      {
        key: "maxToolSteps",
        label: "Max tool steps",
        type: "number",
        default: 40,
        hint: "Maximum function-call executions per run (1-200). Bounds cost and prevents tool loops.",
      },
      {
        key: "maxTurns",
        label: "Max turns",
        type: "number",
        default: 25,
        hint: "Maximum model turns per run before the transcript is returned with the issue left open.",
      },
      {
        key: "timeoutSec",
        label: "Run timeout (seconds)",
        type: "number",
        default: 600,
        hint: "Overall Live session deadline (60-3600s). The session closes when reached.",
      },
      {
        key: "connectTimeoutSec",
        label: "Connect timeout (seconds)",
        type: "number",
        default: 30,
        hint: "WebSocket handshake timeout. A close before setup surfaces as an auth/network error.",
      },
      {
        key: "shellTimeoutSec",
        label: "Shell tool timeout (seconds)",
        type: "number",
        default: 120,
        hint: "Default timeout for run_shell_command (max 600 per call).",
      },
    ],
  };
}
