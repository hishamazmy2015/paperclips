export type GeminiLiveErrorCode =
  | "gemini_live_api_key_missing"
  | "gemini_live_auth_required"
  | "gemini_live_model_not_found"
  | "gemini_live_quota_exceeded"
  | "gemini_live_connect_timeout"
  | "gemini_live_network_unavailable"
  | "gemini_live_session_expired"
  | "gemini_live_max_steps_exceeded"
  | "gemini_live_timeout"
  | "gemini_live_request_failed";

export function classifyGeminiLiveError(message: string): GeminiLiveErrorCode {
  const text = message.toLowerCase();
  if (
    text.includes("api key") ||
    text.includes("api_key") ||
    text.includes("apikey") ||
    text.includes("unauthorized") ||
    text.includes("unauthenticated") ||
    text.includes("permission_denied") ||
    text.includes("permission denied") ||
    text.includes("invalid api key") ||
    text.includes("401") ||
    text.includes("403")
  ) {
    return "gemini_live_auth_required";
  }
  if (text.includes("not_found") || text.includes("not found") || text.includes("404") || text.includes("unknown model") || text.includes("model is not supported") || text.includes("model_not_found")) {
    return "gemini_live_model_not_found";
  }
  if (
    text.includes("quota") ||
    text.includes("rate limit") ||
    text.includes("rate_limit") ||
    text.includes("resource_exhausted") ||
    text.includes("429") ||
    text.includes("billing") ||
    text.includes("exhausted")
  ) {
    return "gemini_live_quota_exceeded";
  }
  if (
    text.includes("econnrefused") ||
    text.includes("econnreset") ||
    text.includes("enotfound") ||
    text.includes("etimedout") ||
    text.includes("socket hang up") ||
    text.includes("network") ||
    text.includes("fetch failed") ||
    text.includes("websocket") && text.includes("closed")
  ) {
    return "gemini_live_network_unavailable";
  }
  if (text.includes("session") && (text.includes("expired") || text.includes("invalid") || text.includes("resum"))) {
    return "gemini_live_session_expired";
  }
  return "gemini_live_request_failed";
}

const AUTH_HINT =
  "Bind a valid GEMINI_API_KEY (or GOOGLE_API_KEY) to this agent via a company secret binding (adapterConfig.env), or set it in the server environment. The key is never logged.";

export function describeGeminiLiveError(code: GeminiLiveErrorCode, detail: string): string {
  switch (code) {
    case "gemini_live_api_key_missing":
      return `Gemini Live API key is missing. ${AUTH_HINT}`;
    case "gemini_live_auth_required":
      return `Gemini Live rejected the API key: ${detail} ${AUTH_HINT}`;
    case "gemini_live_model_not_found":
      return `Gemini Live model unavailable: ${detail} The adapter never substitutes another model; verify gemini-3.1-flash-live-preview is enabled for this API key.`;
    case "gemini_live_quota_exceeded":
      return `Gemini Live quota/rate limit hit: ${detail}`;
    case "gemini_live_connect_timeout":
      return `Timed out connecting to the Gemini Live WebSocket: ${detail}`;
    case "gemini_live_network_unavailable":
      return `Gemini Live network unavailable: ${detail}`;
    case "gemini_live_session_expired":
      return `Gemini Live session expired and could not be resumed: ${detail}`;
    case "gemini_live_max_steps_exceeded":
      return `Gemini Live run stopped: ${detail}`;
    case "gemini_live_timeout":
      return `Gemini Live run timed out: ${detail}`;
    default:
      return `Gemini Live request failed: ${detail}`;
  }
}

export function isRetryableConnectError(code: GeminiLiveErrorCode): boolean {
  return code === "gemini_live_network_unavailable" || code === "gemini_live_connect_timeout";
}
