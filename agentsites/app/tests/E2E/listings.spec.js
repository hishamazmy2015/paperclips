import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { onboard, APP, BASE, PORT, shots } from './onboarding-flow.js';

// Listings management (spec §14) through the real browser: an agent signs up, adds a listing
// with a photo, sees it on the site, then imports a CSV. Livewire drives the forms; the
// photo goes through the same MediaStore path the wizard uses.

const dir = path.dirname(new URL(import.meta.url).pathname);
const url = (host, p = '/') => `http://${host}:${PORT}${p}`;
// site pages carry Cache-Control max-age=60, so after an edit the browser may legitimately show
// its own copy for a minute; these checks fetch from Node (no HTTP cache) with the tenant Host
const siteHtml = async (page, host, p) => (await page.request.get(`http://127.0.0.1:${PORT}${p}`, { headers: { Host: `${host}:${PORT}`, 'Accept-Language': 'en' } })).text();
const articles = (html) => (html.match(/<article\b/g) || []).length;

// a 64×48 JPEG (solid colour) small enough to inline, real enough for the image processor
const JPEG = Buffer.from(
    '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAAwAEADASIAAhEBAxEB/8QAFwABAQEBAAAAAAAAAAAAAAAAAAMEBv/EABoQAQEBAQEBAQAAAAAAAAAAAAABAgMEERL/xAAUAQEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8A6MAKgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAD/2Q==',
    'base64',
);

// one agent for the whole file: the first test signs up and saves the session; the others reuse it
const STATE = path.join(dir, '.listings-state.json');
const stamp = Date.now().toString(36);
const email = `lister-${stamp}@example.com`;
const slug = `lister-${stamp}`;
const host = `${slug}.${BASE}`;

test.describe('listings management', () => {
    test.describe.configure({ mode: 'serial' });

    test('sign up, add a listing with a photo, see it on the site', async ({ page }) => {
        test.setTimeout(90_000);
        await onboard(page, { email, name: 'Lina Lister', whatsapp: '+971501234567', agency: 'Lister Realty', slug });
        await page.context().storageState({ path: STATE });

        // the app home links to the listings section once the site is live
        await page.goto(`${APP}/home`);
        await page.getByTestId('nav-listings').click();
        await expect(page).toHaveURL(`${APP}/listings`);
        await expect(page.getByTestId('empty')).toContainText('sample listings');
        await expect(page.getByTestId('plan-usage')).toContainText('0 of');

        await page.getByTestId('new-listing').click();
        await expect(page).toHaveURL(`${APP}/listings/new`);
        await expect(page.getByTestId('ref')).toHaveValue('REF-0001');
        await page.getByTestId('title_en').fill('Sea-view two bedroom in Marina Gate');
        await page.getByTestId('title_ar').fill('شقة بغرفتي نوم بإطلالة بحرية');
        await page.getByTestId('offering').selectOption('rent');
        await page.getByTestId('property_type').selectOption('apartment');
        await page.getByTestId('price').fill('185000');
        await page.getByTestId('area_sqft').fill('1420');
        await page.getByTestId('bedrooms').fill('2');
        await page.getByTestId('bathrooms').fill('3');
        await page.getByTestId('community').fill('Dubai Marina');
        await page.getByTestId('lat').fill('25.0805');
        await page.getByTestId('lng').fill('55.1403');
        await page.getByTestId('description_en').fill('Bright corner unit with full marina views, vacant now.');
        await page.getByTestId('featured').check();
        await page.getByTestId('photo-input').setInputFiles({ name: 'marina.jpg', mimeType: 'image/jpeg', buffer: JPEG });
        await expect(page.getByTestId('photos').locator('img')).toHaveCount(1, { timeout: 20_000 });
        await expect(page.locator('.photo-cover')).toBeVisible();
        await page.screenshot({ path: path.join(shots, 'listing-form.png'), fullPage: true });
        await page.getByTestId('save').click();

        await expect(page).toHaveURL(`${APP}/listings`);
        await expect(page.getByTestId('flash')).toContainText('Listing saved');
        await expect(page.getByTestId('row-REF-0001')).toContainText('Sea-view two bedroom');
        await expect(page.getByTestId('row-REF-0001').locator('img.listing-thumb')).toHaveAttribute('src', /\/media\/\d+\/listings\//);
        await expect(page.getByTestId('feature-REF-0001')).toHaveAttribute('aria-pressed', 'true');
        await expect(page.getByTestId('plan-usage')).toContainText('1 of');
        await page.screenshot({ path: path.join(shots, 'listings-index.png'), fullPage: true });

        // the public site: the real listing replaced the samples, with its photo, in both locales
        await page.goto(url(host, '/en/listings'));
        await expect(page.locator('article')).toHaveCount(1);
        await expect(page.locator('article h2, article h3').first()).toContainText('Sea-view two bedroom');
        await expect(page.locator('article img').first()).toHaveAttribute('src', /\/media\/\d+\/listings\/.*\.webp$/);
        await page.locator('article a').first().click();
        await expect(page).toHaveURL(url(host, '/en/listings/REF-0001'));
        await expect(page.locator('h1')).toContainText('Sea-view two bedroom');
        expect(await page.locator('script[type="application/ld+json"]').count()).toBeGreaterThan(0);
        await page.goto(url(host, '/ar/listings/REF-0001'));
        await expect(page.locator('h1')).toContainText('شقة بغرفتي نوم');
        // featured: on the home page too
        await page.goto(url(host, '/en'));
        await expect(page.locator('#featured article').first()).toContainText('Sea-view two bedroom');
        // the sitemap carries the real listing
        const sitemap = await page.request.get(`http://127.0.0.1:${PORT}/sitemap.xml`, { headers: { Host: `${host}:${PORT}` } });
        expect(await sitemap.text()).toContain('/en/listings/REF-0001</loc>');
    });

    test.describe('signed in', () => {
    test.use({ storageState: STATE });

    test('hide and show a listing from the index; the site follows immediately', async ({ page }) => {
        await page.goto(`${APP}/listings`);
        await page.getByTestId('hide-REF-0001').click();
        await expect(page.getByTestId('row-REF-0001')).toHaveClass(/is-hidden/);
        expect(articles(await siteHtml(page, host, '/en/listings'))).toBe(0); // no samples come back either
        await page.goto(`${APP}/listings`);
        await page.getByTestId('hide-REF-0001').click();
        await expect(page.getByTestId('row-REF-0001')).not.toHaveClass(/is-hidden/);
        expect(articles(await siteHtml(page, host, '/en/listings'))).toBe(1);
    });

    test('CSV import: template, check, import, rows on the site', async ({ page }) => {
        test.setTimeout(60_000);
        await page.goto(`${APP}/listings/import`);
        // fetched from inside the page: the browser resolves the app host and carries the session
        const template = await page.evaluate(async () => { const r = await fetch('/listings/template.csv'); return { status: r.status, type: r.headers.get('content-type'), text: await r.text() }; });
        expect(template.status).toBe(200);
        expect(template.type).toContain('text/csv');
        const header = template.text.split('\n')[0].trim();
        expect(header.startsWith('ref,title_en,title_ar,offering,property_type,price')).toBeTruthy();

        const csv = [
            header,
            'CSV-1,Three bedroom villa in Arabian Ranches,فيلا ثلاث غرف,sale,villa,4200000,AED,3,4,3200,Arabian Ranches,Dubai,available,true,,,',
            'CSV-2,Studio in JVC,,rent,studio,52000,AED,0,1,410,Jumeirah Village Circle,Dubai,available,,,,',
            'CSV-3,Broken row,,sale,castle,notaprice,AED,,,,,,available,,,,',
        ].join('\n');
        await page.getByTestId('csv-input').setInputFiles({ name: 'listings.csv', mimeType: 'text/csv', buffer: Buffer.from(csv) });
        await expect(page.getByTestId('preview')).toBeVisible({ timeout: 20_000 });
        await expect(page.getByTestId('preview')).toContainText('3 rows: 2 new, 0 updates, 1 with problems');
        await expect(page.getByTestId('preview')).toContainText('CSV-3');
        await page.screenshot({ path: path.join(shots, 'listings-import-preview.png'), fullPage: true });
        await page.getByTestId('import-button').click();
        await expect(page.getByTestId('result')).toContainText('2 created, 0 updated, 1 rows skipped', { timeout: 20_000 });

        await page.goto(`${APP}/listings`);
        await expect(page.getByTestId('row-CSV-1')).toContainText('from CSV');
        await expect(page.getByTestId('row-CSV-2')).toBeVisible();
        await expect(page.getByTestId('plan-usage')).toContainText('3 of');
        expect(articles(await siteHtml(page, host, '/en/listings'))).toBe(3);
        const forSale = await siteHtml(page, host, '/en/listings?offering=sale');
        expect(articles(forSale)).toBe(1);
        expect(forSale).toContain('Arabian Ranches');
    });
});
});
