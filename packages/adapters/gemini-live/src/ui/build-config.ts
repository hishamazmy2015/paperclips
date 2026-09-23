import { buildAdapterEnvConfig, type CreateConfigValues } from "@paperclipai/adapter-utils";
import { DEFAULT_GEMINI_LIVE_MODEL } from "../index.js";

export function buildGeminiLiveConfig(v: CreateConfigValues): Record<string, unknown> {
  const ac: Record<string, unknown> = {};
  if (v.cwd) ac.cwd = v.cwd;
  if (v.instructionsFilePath) ac.instructionsFilePath = v.instructionsFilePath;
  ac.model = v.model || DEFAULT_GEMINI_LIVE_MODEL;
  // The Live session is deadline-bounded; default to 600s when unset.
  ac.timeoutSec = 600;
  ac.graceSec = 20;
  const env = buildAdapterEnvConfig(v.envBindings, v.envVars);
  if (Object.keys(env).length > 0) ac.env = env;
  const schemaValues = v.adapterSchemaValues ?? {};
  for (const key of ["temperature", "maxOutputTokens", "maxToolSteps", "maxTurns", "connectTimeoutSec", "shellTimeoutSec", "timeoutSec"]) {
    const value = schemaValues[key];
    if (typeof value === "number" && Number.isFinite(value)) ac[key] = value;
  }
  if (typeof schemaValues.enableVoice === "boolean") {
    ac.enableVoice = schemaValues.enableVoice;
  }
  if (typeof schemaValues.voiceName === "string" && schemaValues.voiceName.trim()) {
    ac.voiceName = schemaValues.voiceName.trim();
  }
  return ac;
}
