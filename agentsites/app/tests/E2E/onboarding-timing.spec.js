import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { onboard } from './onboarding.spec.js';

// Phase 2 DoD (spec §21): median landing → published < 120 s over 10 runs. Opt in with
// E2E_TIMING_RUNS=10 (npm run e2e:timing); writes tests/E2E/timing.json.

const runs = Number(process.env.E2E_TIMING_RUNS || 0);
const dir = path.dirname(new URL(import.meta.url).pathname);

test.describe('onboarding timing', () => {
    test.skip(runs === 0, 'set E2E_TIMING_RUNS to run');
    test.setTimeout(runs * 60_000);

    test(`median of ${runs} runs from landing to published`, async ({ browser }) => {
        const seconds = [];
        for (let i = 0; i < runs; i++) {
            const context = await browser.newContext({ viewport: { width: 375, height: 667 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2, locale: 'en-GB' });
            const page = await context.newPage();
            const stamp = `${Date.now().toString(36)}${i}`;
            const started = performance.now();
            await onboard(page, { email: `timing-${stamp}@example.com`, name: `Timing Agent ${i + 1}`, whatsapp: `+97150${String(1000000 + i).padStart(7, '0')}`, agency: 'haus & haus' });
            seconds.push((performance.now() - started) / 1000);
            await context.close();
        }
        const sorted = [...seconds].sort((a, b) => a - b);
        const median = sorted.length % 2 ? sorted[(sorted.length - 1) / 2] : (sorted[sorted.length / 2 - 1] + sorted[sorted.length / 2]) / 2;
        const result = { runs, seconds: seconds.map((s) => Math.round(s * 10) / 10), median: Math.round(median * 10) / 10, target_seconds: 120, recorded_at: new Date().toISOString() };
        fs.writeFileSync(path.join(dir, 'timing.json'), JSON.stringify(result, null, 2));
        console.log(`[timing] median ${result.median} s over ${runs} runs (${result.seconds.join(', ')})`);
        expect(median).toBeLessThan(120);
    });
});
