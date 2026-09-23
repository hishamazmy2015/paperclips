import { describe, expect, it } from "vitest";
import { DEFAULT_VOICE_SAMPLE_RATE, parsePcmSampleRate, pcmToWav } from "./audio.js";

describe("parsePcmSampleRate", () => {
  it("parses the rate from a Live PCM mime type", () => {
    expect(parsePcmSampleRate("audio/pcm;rate=24000")).toBe(24000);
    expect(parsePcmSampleRate("audio/pcm; rate=16000")).toBe(16000);
  });

  it("returns null when the rate is missing or out of range", () => {
    expect(parsePcmSampleRate("audio/pcm")).toBeNull();
    expect(parsePcmSampleRate("")).toBeNull();
    expect(parsePcmSampleRate("audio/pcm;rate=999999")).toBeNull();
  });
});

describe("pcmToWav", () => {
  it("writes a valid 44-byte RIFF/WAVE header around the PCM payload", () => {
    const pcm = Buffer.alloc(1000, 7);
    const wav = pcmToWav(pcm, 24000);
    expect(wav.length).toBe(44 + 1000);
    expect(wav.toString("ascii", 0, 4)).toBe("RIFF");
    expect(wav.readUInt32LE(4)).toBe(36 + 1000);
    expect(wav.toString("ascii", 8, 12)).toBe("WAVE");
    expect(wav.toString("ascii", 12, 16)).toBe("fmt ");
    expect(wav.readUInt16LE(20)).toBe(1); // PCM format
    expect(wav.readUInt16LE(22)).toBe(1); // mono
    expect(wav.readUInt32LE(24)).toBe(24000);
    expect(wav.readUInt32LE(28)).toBe(48000); // byte rate = 24000 * 1 * 16/8
    expect(wav.readUInt16LE(32)).toBe(2); // block align
    expect(wav.readUInt16LE(34)).toBe(16);
    expect(wav.toString("ascii", 36, 40)).toBe("data");
    expect(wav.readUInt32LE(40)).toBe(1000);
    expect(wav.subarray(44).equals(pcm)).toBe(true);
  });

  it("defaults to the Live output sample rate", () => {
    const wav = pcmToWav(Buffer.alloc(4));
    expect(wav.readUInt32LE(24)).toBe(DEFAULT_VOICE_SAMPLE_RATE);
  });
});
