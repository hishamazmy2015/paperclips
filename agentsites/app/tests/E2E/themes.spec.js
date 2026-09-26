import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

// The three V1 themes render distinct layouts from the same tenant config (spec §10). The sites
// come from tests/E2E/prepare.sh (lh-atlas / lh-marina / lh-palm, the Lighthouse gate sites).

const PORT = process.env.E2E_PORT || '8123';
const BASE = process.env.PLATFORM_BASE_DOMAIN || 'example.test';
const url = (host, p = '/') => `http://${host}:${PORT}${p}`;
const dir = path.dirname(new URL(import.meta.url).pathname);
const shots = path.join(dir, 'screenshots');
fs.mkdirSync(shots, { recursive: true });

const THEMES = {
    atlas: { name: 'Lighthouse Atlas', hero: 'section.bg-secondary h1', listings: 'article' },
    marina: { name: 'Lighthouse Marina', hero: '.split-hero h1', listings: '.row-list article' },
    palm: { name: 'Lighthouse Palm', hero: 'h1.display', listings: 'article' },
};

test.describe('themes', () => {
    for (const [theme, t] of Object.entries(THEMES)) {
        const host = `lh-${theme}.${BASE}`;

        test(`${theme}: home renders its own layout in both locales without horizontal scroll`, async ({ page }) => {
            for (const locale of ['en', 'ar']) {
                const response = await page.goto(url(host, `/${locale}`));
                expect(response.ok()).toBeTruthy();
                await expect(page.locator('html')).toHaveAttribute('lang', locale);
                await expect(page.locator('html')).toHaveAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
                await expect(page.locator(t.hero)).toContainText(t.name);
                // every theme ships the same conversion path: a tappable WhatsApp CTA plus the sticky bar
                await expect(page.locator('a[href^="https://wa.me/971"]:visible').first()).toBeVisible();
                await expect(page.locator('div.fixed.bottom-0 a[href^="https://wa.me/"]')).toBeVisible();
                // the theme stylesheet (one CSS entry per theme) is what styles the page
                await expect(page.locator(`link[rel="stylesheet"][href*="/build/assets/theme-"]`).first()).toHaveAttribute('href', /theme-/);
                const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
                expect(overflow).toBeFalsy();
                await page.screenshot({ path: path.join(shots, `theme-${theme}-${locale}.png`), fullPage: true });
            }
        });

        test(`${theme}: listings, one listing, about, area and contact pages`, async ({ page }) => {
            await page.goto(url(host, '/en/listings'));
            await expect(page.locator(t.listings).first()).toBeVisible();
            await page.locator(`${t.listings} a`).first().click();
            await expect(page).toHaveURL(/\/en\/listings\/DEMO-\d+$/);
            await expect(page.locator('h1')).toBeVisible();
            for (const p of ['/en/about', '/en/areas/downtown', '/en/contact']) {
                const response = await page.goto(url(host, p));
                expect(response.ok(), p).toBeTruthy();
                await expect(page.locator('h1')).toBeVisible();
            }
            const missing = await page.goto(url(host, '/en/nope'));
            expect(missing.status()).toBe(404);
            await expect(page.locator('h1')).toContainText('Page not found');
        });
    }

    test('palm puts testimonials right after the hero; marina and atlas keep the config order', async ({ page }) => {
        await page.goto(url(`lh-palm.${BASE}`, '/en'));
        const ids = await page.locator('main section[id]').evaluateAll((els) => els.map((e) => e.id));
        expect(ids[0]).toBe('hero');
        expect(ids[1]).toBe('testimonials');
        for (const theme of ['marina', 'atlas']) {
            await page.goto(url(`lh-${theme}.${BASE}`, '/en'));
            const order = await page.locator('main section[id]').evaluateAll((els) => els.map((e) => e.id));
            expect(order[0]).toBe('hero');
            expect(order.indexOf('testimonials'), theme).toBeGreaterThan(order.indexOf('featured'));
        }
    });

    test('SEO surface: sitemap, robots, canonical, hreflang and structured data', async ({ page }) => {
        const host = `lh-marina.${BASE}`;
        const sitemap = await page.request.get(`http://127.0.0.1:${PORT}/sitemap.xml`, { headers: { Host: `${host}:${PORT}` } });
        expect(sitemap.status()).toBe(200);
        expect(sitemap.headers()['content-type']).toContain('xml');
        const xml = await sitemap.text();
        expect(xml).toContain(`/en/listings</loc>`);
        expect(xml).toContain('hreflang="ar"');
        expect(xml).not.toContain('DEMO-'); // sample listings never reach the sitemap

        const robots = await page.request.get(`http://127.0.0.1:${PORT}/robots.txt`, { headers: { Host: `${host}:${PORT}` } });
        expect(robots.status()).toBe(200);
        expect(await robots.text()).toContain('Sitemap: ');

        await page.goto(url(host, '/en'));
        await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', new RegExp(`^https?://${host.replace('.', '\\.')}(:${PORT})?/en$`));
        await expect(page.locator('link[rel="alternate"][hreflang="ar"]')).toHaveCount(1);
        await expect(page.locator('link[rel="alternate"][hreflang="x-default"]')).toHaveCount(1);
        await expect(page.locator('meta[property="og:title"]')).toHaveCount(1);
        const jsonLd = JSON.parse(await page.locator('script[type="application/ld+json"]').first().textContent());
        expect(jsonLd['@type']).toEqual(expect.arrayContaining(['RealEstateAgent']));
        expect(jsonLd.name).toBe('Lighthouse Marina');
        expect(await page.locator('title').textContent()).toContain('Lighthouse Marina');
    });

    test('a live page is served from the full-page cache with an ETag', async ({ page }) => {
        const host = `lh-atlas.${BASE}`;
        const headers = { Host: `${host}:${PORT}`, 'Accept-Language': 'en' };
        const first = await page.request.get(`http://127.0.0.1:${PORT}/en`, { headers });
        expect(first.status()).toBe(200);
        expect(first.headers()['cache-control']).toContain('max-age=60');
        const etag = first.headers()['etag'];
        expect(etag).toMatch(/^"[0-9a-f]{32}"$/);
        const second = await page.request.get(`http://127.0.0.1:${PORT}/en`, { headers });
        expect(second.headers()['x-cache']).toBe('HIT');
        const revalidated = await page.request.get(`http://127.0.0.1:${PORT}/en`, { headers: { ...headers, 'If-None-Match': etag } });
        expect(revalidated.status()).toBe(304);
    });
});
