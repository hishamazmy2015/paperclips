import { describe, expect, it } from "vitest";
import { buildGeminiLiveConfig } from "./build-config.js";
import { DEFAULT_GEMINI_LIVE_MODEL } from "../index.js";

describe("buildGeminiLiveConfig", () => {
  it("builds default config when no custom values provided", () => {
    const config = buildGeminiLiveConfig({} as unknown as Parameters<typeof buildGeminiLiveConfig>[0]);
    expect(config.model).toBe(DEFAULT_GEMINI_LIVE_MODEL);
    expect(config.timeoutSec).toBe(600);
    expect(config.graceSec).toBe(20);
  });

  it("passes voice settings and schema values correctly", () => {
    const config = buildGeminiLiveConfig({
      model: "gemini-3.1-flash-live-preview",
      cwd: "/workspace",
      adapterSchemaValues: {
        enableVoice: true,
        voiceName: "Aoede",
        temperature: 0.5,
        maxToolSteps: 30,
        timeoutSec: 900,
      },
    } as unknown as Parameters<typeof buildGeminiLiveConfig>[0]);

    expect(config.model).toBe("gemini-3.1-flash-live-preview");
    expect(config.cwd).toBe("/workspace");
    expect(config.enableVoice).toBe(true);
    expect(config.voiceName).toBe("Aoede");
    expect(config.temperature).toBe(0.5);
    expect(config.maxToolSteps).toBe(30);
    expect(config.timeoutSec).toBe(900);
  });
});
