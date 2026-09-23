import type { AdapterEnvironmentTestContext, AdapterEnvironmentTestResult } from "@paperclipai/adapter-utils";
import { DEFAULT_GEMINI_LIVE_MODEL } from "../index.js";

function hasApiKey(config: Record<string, unknown>): boolean {
  const env = (config.env ?? {}) as Record<string, unknown>;
  for (const key of ["GEMINI_API_KEY", "GOOGLE_API_KEY"]) {
    const value = env[key];
    if (typeof value === "string" && value.trim().length > 0) return true;
    if (typeof value === "object" && value !== null) {
      const record = value as Record<string, unknown>;
      // Secret bindings resolve at run time; a declared binding counts as configured.
      if (record.type === "secret_ref" || record.type === "user_secret_ref") return true;
      if (record.type === "plain" && typeof record.value === "string" && record.value.trim().length > 0) return true;
    }
  }
  if (typeof process.env.GEMINI_API_KEY === "string" && process.env.GEMINI_API_KEY.trim().length > 0) return true;
  if (typeof process.env.GOOGLE_API_KEY === "string" && process.env.GOOGLE_API_KEY.trim().length > 0) return true;
  return false;
}

export async function testEnvironment(
  ctx: AdapterEnvironmentTestContext,
): Promise<AdapterEnvironmentTestResult> {
  const model = typeof ctx.config.model === "string" && ctx.config.model.trim().length > 0
    ? ctx.config.model.trim()
    : DEFAULT_GEMINI_LIVE_MODEL;
  const checks: AdapterEnvironmentTestResult["checks"] = [];
  try {
    await import("@google/genai");
    checks.push({ code: "genai_sdk_available", level: "info", message: "@google/genai SDK is installed; Live API transport available." });
  } catch {
    return {
      adapterType: ctx.adapterType,
      status: "fail",
      testedAt: new Date().toISOString(),
      checks: [{ code: "genai_sdk_missing", level: "error", message: "@google/genai is not installed.", hint: "Install @google/genai in the server environment." }],
    };
  }
  if (model !== DEFAULT_GEMINI_LIVE_MODEL) {
    checks.push({
      code: "model_override",
      level: "warn",
      message: `Configured model "${model}" overrides the qualified ${DEFAULT_GEMINI_LIVE_MODEL}.`,
      hint: "Only gemini-3.1-flash-live-preview is qualified; other models are untested and the adapter never substitutes silently.",
    });
  } else {
    checks.push({ code: "model_pinned", level: "info", message: `Live model pinned to ${DEFAULT_GEMINI_LIVE_MODEL}.` });
  }
  if (hasApiKey(ctx.config)) {
    checks.push({ code: "api_key_configured", level: "info", message: "Gemini API key binding detected (value never displayed)." });
  } else {
    checks.push({
      code: "api_key_missing",
      level: "error",
      message: "No GEMINI_API_KEY (or GOOGLE_API_KEY) binding found.",
      hint: "Bind GEMINI_API_KEY as a company secret in adapterConfig.env. Runs fail closed without it.",
    });
  }
  const status = checks.some((check) => check.level === "error") ? "fail" : checks.some((check) => check.level === "warn") ? "warn" : "pass";
  return { adapterType: ctx.adapterType, status, testedAt: new Date().toISOString(), checks };
}
