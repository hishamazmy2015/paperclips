import type { TranscriptEntry } from "@paperclipai/adapter-utils";

export function parseGeminiLiveStdoutLine(line: string, ts: string): TranscriptEntry[] {
  const trimmed = line.trim();
  if (!trimmed) return [];
  if (trimmed.startsWith("[gemini-live] assistant:")) {
    return [{ kind: "assistant", ts, text: trimmed.slice("[gemini-live] assistant:".length).trim() }];
  }
  if (trimmed.startsWith("[gemini-live] tool_call")) {
    return [{ kind: "system", ts, text: trimmed }];
  }
  if (trimmed.startsWith("[gemini-live] tool_result")) {
    return [{ kind: "system", ts, text: trimmed }];
  }
  if (trimmed.startsWith("[gemini-live] task_complete")) {
    return [{ kind: "system", ts, text: trimmed }];
  }
  if (trimmed.startsWith("[gemini-live]")) {
    return [{ kind: "stderr", ts, text: trimmed }];
  }
  return [{ kind: "stdout", ts, text: trimmed }];
}
