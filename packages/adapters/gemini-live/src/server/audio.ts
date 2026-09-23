/** Gemini Live audio output is 16-bit little-endian PCM, mono, typically 24 kHz. */

/** Hard cap on captured PCM bytes per run (~23 min at 24 kHz mono 16-bit). */
export const MAX_VOICE_PCM_BYTES = 64 * 1024 * 1024;

export const DEFAULT_VOICE_SAMPLE_RATE = 24000;

/** Parse the sample rate from a Live inlineData mime type like "audio/pcm;rate=24000". */
export function parsePcmSampleRate(mimeType: string): number | null {
  const match = /rate=(\d{4,6})/.exec(mimeType);
  if (!match) return null;
  const rate = Number(match[1]);
  return Number.isFinite(rate) && rate >= 8000 && rate <= 192000 ? rate : null;
}

/** Wrap raw 16-bit LE mono PCM in a standard 44-byte WAV header. */
export function pcmToWav(
  pcm: Buffer,
  sampleRate: number = DEFAULT_VOICE_SAMPLE_RATE,
  channels = 1,
  bitsPerSample = 16,
): Buffer {
  const header = Buffer.alloc(44);
  const byteRate = (sampleRate * channels * bitsPerSample) / 8;
  const blockAlign = (channels * bitsPerSample) / 8;
  header.write("RIFF", 0, "ascii");
  header.writeUInt32LE(36 + pcm.length, 4);
  header.write("WAVE", 8, "ascii");
  header.write("fmt ", 12, "ascii");
  header.writeUInt32LE(16, 16);
  header.writeUInt16LE(1, 20);
  header.writeUInt16LE(channels, 22);
  header.writeUInt32LE(sampleRate, 24);
  header.writeUInt32LE(byteRate, 28);
  header.writeUInt16LE(blockAlign, 32);
  header.writeUInt16LE(bitsPerSample, 34);
  header.write("data", 36, "ascii");
  header.writeUInt32LE(pcm.length, 40);
  return Buffer.concat([header, pcm]);
}
