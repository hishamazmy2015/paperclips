// Lighthouse gates (spec §10, §19): three themes × two locales on a mobile profile with simulated
// 4G. Thresholds: performance ≥ 90, accessibility ≥ 95, SEO ≥ 95. Run after tests/E2E/prepare.sh
// (it creates the lh-atlas / lh-marina / lh-palm sites): `npm run lighthouse`.
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';
import lighthouse from 'lighthouse';
import { launch } from 'chrome-launcher';

const PORT = process.env.E2E_PORT || '8123';
const BASE = process.env.PLATFORM_BASE_DOMAIN || 'example.test';
const dir = path.dirname(new URL(import.meta.url).pathname);
const THRESHOLDS = { performance: 0.9, accessibility: 0.95, seo: 0.95 };
const THEMES = ['atlas', 'marina', 'palm'];
const LOCALES = ['en', 'ar'];

async function up() {
    try { return (await fetch(`http://127.0.0.1:${PORT}/up`)).ok; } catch { return false; }
}

async function ensureServer() {
    if (await up()) return null;
    const server = spawn('bash', ['tests/E2E/serve.sh'], { cwd: path.resolve(dir, '../..'), stdio: 'ignore', detached: true });
    for (let i = 0; i < 60; i++) {
        await new Promise((r) => setTimeout(r, 500));
        if (await up()) return server;
    }
    throw new Error('the E2E server did not start');
}

const server = await ensureServer();
const chrome = await launch({
    chromePath: process.env.PW_CHROMIUM || process.env.CHROME_PATH || undefined,
    chromeFlags: ['--headless=new', '--no-sandbox', '--disable-gpu', `--host-resolver-rules=MAP *.${BASE} 127.0.0.1, MAP ${BASE} 127.0.0.1`],
});

const results = [];
try {
    for (const theme of THEMES) {
        for (const locale of LOCALES) {
            const url = `http://lh-${theme}.${BASE}:${PORT}/${locale}`;
            const run = await lighthouse(url, { port: chrome.port, output: 'json', logLevel: 'error', onlyCategories: ['performance', 'accessibility', 'seo', 'best-practices'] });
            const lhr = run.lhr;
            const scores = Object.fromEntries(Object.entries(lhr.categories).map(([k, v]) => [k, Math.round((v.score ?? 0) * 100)]));
            const failed = Object.entries(THRESHOLDS).filter(([k, min]) => (lhr.categories[k]?.score ?? 0) < min).map(([k]) => k);
            const row = {
                theme, locale, url, scores,
                lcp_ms: Math.round(lhr.audits['largest-contentful-paint']?.numericValue ?? 0),
                cls: Number((lhr.audits['cumulative-layout-shift']?.numericValue ?? 0).toFixed(3)),
                total_kb: Math.round((lhr.audits['total-byte-weight']?.numericValue ?? 0) / 1024),
                failed,
            };
            results.push(row);
            console.log(`[lighthouse] ${theme}/${locale}: perf ${scores.performance} a11y ${scores.accessibility} seo ${scores.seo} bp ${scores['best-practices']} · LCP ${row.lcp_ms} ms · CLS ${row.cls}${failed.length ? '  ✘ ' + failed.join(', ') : '  ✓'}`);
            if (failed.length) {
                const audits = Object.values(lhr.audits).filter((a) => a.score !== null && a.score < 0.9 && !['performance-budget', 'timing-budget'].includes(a.id)).slice(0, 12);
                for (const a of audits) console.log(`    - ${a.id}: ${Math.round((a.score ?? 0) * 100)} ${a.displayValue ?? ''}`);
            }
        }
    }
} finally {
    await chrome.kill();
    if (server) { try { process.kill(-server.pid); } catch { /* already gone */ } }
}

const pass = results.every((r) => r.failed.length === 0);
fs.writeFileSync(path.join(dir, 'lighthouse.json'), JSON.stringify({ thresholds: THRESHOLDS, pass, results, recorded_at: new Date().toISOString() }, null, 2));
console.log(pass ? '[lighthouse] all gates pass' : '[lighthouse] GATES FAILED');
process.exit(pass ? 0 : 1);
