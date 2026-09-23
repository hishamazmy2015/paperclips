import pc from "picocolors";

export function printGeminiLiveStreamEvent(raw: string, _debug: boolean): void {
  const line = raw.trim();
  if (!line) return;
  if (line.startsWith("[gemini-live] assistant:")) {
    console.log(pc.green(line));
    return;
  }
  if (line.startsWith("[gemini-live] tool_call")) {
    console.log(pc.yellow(line));
    return;
  }
  if (line.startsWith("[gemini-live] tool_result")) {
    console.log(pc.gray(line));
    return;
  }
  if (line.startsWith("[gemini-live] task_complete")) {
    console.log(pc.cyan(line));
    return;
  }
  if (line.startsWith("[gemini-live]")) {
    console.log(pc.gray(line));
    return;
  }
  console.log(line);
}
