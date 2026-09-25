import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

// Real-browser checks on a mobile viewport (spec §13 global rules, §10, §19). The data comes from
// tests/E2E/prepare.sh: E2E_SITES generated agents imported as live sites, plus one draft.

const PORT = process.env.E2E_PORT || '8123';
const BASE = process.env.PLATFORM_BASE_DOMAIN || 'example.test';
const url = (host, p = '/') => `http://${host}:${PORT}${p}`;
const dir = path.dirname(new URL(import.meta.url).pathname);
const shots = path.join(dir, 'screenshots');
fs.mkdirSync(shots, { recursive: true });

const list = JSON.parse(fs.readFileSync(path.join(dir, '.sites.json'), 'utf8'));
const draft = JSON.parse(fs.readFileSync(path.join(dir, '.draft.json'), 'utf8'));
const live = list.sites.filter((s) => s.status === 'live');

// deterministic sample so a failure is reproducible
function sample(items, n, seed = 7) {
    const out = [];
    let x = seed;
    const pool = [...items];
    while (out.length < n && pool.length) {
        x = (x * 1103515245 + 12345) % 2147483648;
        out.push(pool.splice(x % pool.length, 1)[0]);
    }
    return out;
}

test.describe('generated sites', () => {
    test('the data set is many different agents, not one config repeated', () => {
        expect(live.length).toBeGreaterThanOrEqual(10);
        const names = new Set(live.map((s) => s.name));
        const palettes = new Set(live.map((s) => s.palette));
        const locales = new Set(live.map((s) => s.locale));
        expect(names.size).toBeGreaterThan(Math.min(100, live.length / 3));
        expect(palettes.size).toBeGreaterThanOrEqual(Math.min(6, live.length));
        expect([...locales].sort()).toEqual(['ar', 'en']);
        expect(new Set(live.map((s) => s.slug)).size).toBe(live.length);
    });

    for (const site of sample(live, 12)) {
        test(`${site.slug} opens on its own host and renders its own config`, async ({ page }) => {
            // the browser is en-GB: Accept-Language wins over the site default (DECISIONS #24)
            const response = await page.goto(url(site.host));
            expect(response.ok()).toBeTruthy();
            await expect(page).toHaveURL(/\/en$/);
            await expect(page.locator('html')).toHaveAttribute('lang', 'en');
            await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
            await expect(page.locator('h1')).toContainText(site.name);
            // a WhatsApp link the visitor can actually tap (the header button is desktop-only)
            await expect(page.locator('a[href^="https://wa.me/971"]:visible').first()).toBeVisible();
            // the sticky WhatsApp bar exists on phones
            await expect(page.locator('div.fixed.bottom-0 a[href^="https://wa.me/"]')).toBeVisible();
            // the tenant palette reached the DOM
            const primary = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--c-primary').trim());
            expect(primary).toMatch(/^#[0-9a-f]{6}$/i);
            // no horizontal scroll at 375 px
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
            expect(overflow).toBeFalsy();

            // Arabic: right-to-left, same agent, same config
            await page.goto(url(site.host, '/ar'));
            await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
            await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
            await expect(page.locator('h1')).toContainText(site.name);
            // a browser preferring nothing the site offers lands on the site's own default locale
            // (page.request runs in Node, outside Chromium's resolver rules: target the port and set Host)
            const fallback = await page.request.get(`http://127.0.0.1:${PORT}/`, { headers: { Host: `${site.host}:${PORT}`, 'Accept-Language': 'fr-FR' }, maxRedirects: 0 });
            expect(fallback.status()).toBe(302);
            // Laravel sends an absolute Location (scheme + tenant host + path)
            expect(new URL(fallback.headers()['location']).pathname).toBe(`/${site.locale}`);
        });
    }

    test('listings and a listing page work; demo listings carry no structured data', async ({ page }) => {
        const site = sample(live, 1, 11)[0];
        await page.goto(url(site.host, '/en/listings'));
        await expect(page.locator('article').first()).toBeVisible();
        await page.locator('article a').first().click();
        await expect(page).toHaveURL(/\/en\/listings\/DEMO-\d+$/);
        await expect(page.locator('h1')).toBeVisible();
        await expect(page.locator('a[href^="https://wa.me/971"]:visible').first()).toBeVisible();
        expect(await page.locator('script[type="application/ld+json"]').count()).toBe(0);
    });

    test('screenshots: three sites, both locales (tests/E2E/screenshots)', async ({ page }) => {
        for (const site of sample(live, 3, 5)) {
            for (const locale of ['en', 'ar']) {
                await page.goto(url(site.host, `/${locale}`));
                await page.waitForLoadState('networkidle');
                await page.screenshot({ path: path.join(shots, `${site.slug}-${locale}.png`), fullPage: true });
            }
        }
        await page.goto(url(sample(live, 1, 5)[0].host, '/en/listings'));
        await page.screenshot({ path: path.join(shots, 'listings-en.png'), fullPage: true });
    });
});

test.describe('platform behaviour', () => {
    test('a draft is hidden without its preview token', async ({ page }) => {
        const host = `draft-agent.${BASE}`;
        const hidden = await page.goto(url(host, '/en'));
        expect(hidden.status()).toBe(404);
        const previewUrl = new URL(draft.preview_url);
        const shown = await page.goto(url(host, `/en${previewUrl.search}`));
        expect(shown.status()).toBe(200);
        await expect(page.locator('h1')).toContainText('Draft Agent');
        await page.screenshot({ path: path.join(shots, 'draft-preview.png'), fullPage: true });
    });

    test('an unknown host is a 404', async ({ page }) => {
        const response = await page.goto(url(`nobody-here.${BASE}`, '/en'));
        expect(response.status()).toBe(404);
    });

    test('the landing page serves the ar/en headline on the apex host', async ({ page }) => {
        await page.goto(url(BASE, '/'));
        await expect(page.locator('h1')).toContainText('live in 2 minutes');
        await page.goto(url(BASE, '/?lang=ar'));
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await page.screenshot({ path: path.join(shots, 'landing-ar.png') });
    });
});
