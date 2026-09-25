import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

// Acceptance test A2 (spec §21) on a phone-sized Chromium: landing → sign in with an emailed code →
// S2 → S3 → S4 → published in one flow, ≤ 6 typed fields, the site live on its own host afterwards,
// and the welcome WhatsApp handed to the notifier. Mail and WhatsApp go to file sinks (env.sh).

const PORT = process.env.E2E_PORT || '8123';
const BASE = process.env.PLATFORM_BASE_DOMAIN || 'example.test';
const APP = `http://app.${BASE}:${PORT}`;
const dir = path.dirname(new URL(import.meta.url).pathname);
const appRoot = path.resolve(dir, '../..');
const mailSink = path.join(appRoot, 'storage/app/private/mail-sink');
const waSink = path.join(appRoot, 'storage/app/private/whatsapp-sink');
const shots = path.join(dir, 'screenshots');
fs.mkdirSync(shots, { recursive: true });

function newestJson(sinkDir, predicate = () => true) {
    if (!fs.existsSync(sinkDir)) return null;
    const files = fs.readdirSync(sinkDir).filter((f) => f.endsWith('.json')).sort();
    for (const file of files.reverse()) {
        const data = JSON.parse(fs.readFileSync(path.join(sinkDir, file), 'utf8'));
        if (predicate(data)) return data;
    }
    return null;
}

async function waitForMail(to) {
    await expect.poll(() => newestJson(mailSink, (m) => m.to.includes(to)), { timeout: 15_000 }).not.toBeNull();
    return newestJson(mailSink, (m) => m.to.includes(to));
}

export async function onboard(page, { email, name, whatsapp, agency, slug, areas = ['Downtown', 'Marina'], screenshots = false, browser = null }) {
    const typed = [];
    const typeInto = async (locator, value) => { typed.push(value); await locator.fill(value); };

    // S0 → S1
    await page.goto(`http://${BASE}:${PORT}/`);
    await page.getByTestId('landing-cta').click();
    await expect(page).toHaveURL(`${APP}/start`);
    if (screenshots) await page.screenshot({ path: path.join(shots, 'onboarding-s1.png') });
    await typeInto(page.getByTestId('email'), email);
    await page.getByTestId('send-code').click();
    await expect(page).toHaveURL(`${APP}/start/code`);

    const mail = await waitForMail(email);
    const code = mail.subject.match(/\d{6}/)[0];
    expect(mail.text).toContain('/auth/magic/');
    await typeInto(page.getByTestId('code'), code); // auto-submits at 6 digits
    await expect(page).toHaveURL(new RegExp(`${APP}/onboarding`));

    // S2 — about you (1/3)
    await expect(page.locator('h1')).toHaveText('About you');
    await expect(page.getByTestId('wizard')).toHaveAttribute('data-step', '1');
    const nameInput = page.getByTestId('name');
    await typeInto(nameInput, name);
    await typeInto(page.getByTestId('whatsapp'), whatsapp);
    await expect(page.getByTestId('whatsapp-ok')).toBeVisible();
    await typeInto(page.getByTestId('agency'), agency);
    if (screenshots) await page.screenshot({ path: path.join(shots, 'onboarding-s2.png') });
    await expect(page.getByTestId('next')).toBeEnabled();
    await page.getByTestId('next').click();

    // S3 — your website (2/3)
    await expect(page.locator('h1')).toHaveText('Your website');
    const slugInput = page.getByTestId('slug');
    await expect(slugInput).not.toHaveValue('');
    if (slug) { await typeInto(slugInput, slug); }
    await expect(page.getByTestId('slug-available')).toBeVisible();
    const finalSlug = await slugInput.inputValue();
    await expect(page.getByTestId('slug-url')).toHaveText(`https://${finalSlug}.${BASE}`);
    await expect(page.getByTestId('theme-atlas')).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByTestId('theme-atlas').locator('b')).toHaveText(name); // the card renders the agent's own name
    await page.getByTestId('palette-navy').click();
    await expect(page.getByTestId('palette-navy')).toHaveAttribute('aria-pressed', 'true');
    if (screenshots) await page.screenshot({ path: path.join(shots, 'onboarding-s3.png') });
    await page.getByTestId('next').click();

    // S4 — go live (3/3)
    await expect(page.locator('h1')).toHaveText('Go live');
    for (const area of areas) await page.getByTestId('areas').getByRole('button', { name: area, exact: true }).click();
    const frame = page.frameLocator('[data-test="preview"] iframe');
    await expect(frame.locator('h1')).toContainText(name, { timeout: 15_000 });
    await expect(frame.locator('html')).toHaveAttribute('lang', 'en');
    if (screenshots) await page.screenshot({ path: path.join(shots, 'onboarding-s4.png') });
    await page.getByTestId('publish').click();

    // S5 — success
    await expect(page).toHaveURL(`${APP}/onboarding/success`, { timeout: 20_000 });
    await expect(page.locator('h1')).toContainText('live');
    await expect(page.getByTestId('site-url')).toContainText(`${finalSlug}.${BASE}`);
    await expect(page.getByTestId('qr').locator('svg')).toBeVisible();
    await expect(page.getByTestId('share-whatsapp')).toHaveAttribute('href', `${APP}/share/whatsapp`);
    await expect(page.getByTestId('checklist').locator('li')).toHaveCount(5);
    if (screenshots) await page.screenshot({ path: path.join(shots, 'onboarding-s5.png'), fullPage: true });

    return { slug: finalSlug, typed };
}

test.describe('onboarding (A2)', () => {
    test('a new agent goes from the landing page to a live site on a phone', async ({ page }) => {
        const stamp = Date.now().toString(36);
        const email = `agent-${stamp}@example.com`;
        const { slug, typed } = await onboard(page, { email, name: 'Maryam Al Suwaidi', whatsapp: '050 765 4321', agency: 'Betterhomes', screenshots: true });

        // ≤ 6 typed fields (spec §13): email, code, name, WhatsApp, agency (the slug was prefilled)
        expect(typed.length).toBeLessThanOrEqual(6);

        // the site is live on its own host, with the agent's choices
        const site = await page.context().newPage();
        const response = await site.goto(`http://${slug}.${BASE}:${PORT}/en`);
        expect(response.ok()).toBeTruthy();
        await expect(site.locator('h1')).toContainText('Maryam Al Suwaidi');
        await expect(site.locator('a[href^="https://wa.me/971507654321"]').first()).toBeAttached();
        expect(await site.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--c-primary').trim())).toBe('#1e3a8a');
        await expect(site.locator('text=Downtown').first()).toBeVisible();
        await site.screenshot({ path: path.join(shots, 'onboarding-live-site.png'), fullPage: true });

        // the welcome WhatsApp reached the notifier with the site URL (spec §12 publish, A2)
        await expect.poll(() => newestJson(waSink, (m) => m.to === '+971507654321'), { timeout: 10_000 }).not.toBeNull();
        const welcome = newestJson(waSink, (m) => m.to === '+971507654321');
        expect(welcome.text).toContain(`${slug}.${BASE}`);

        // "Open my site" records the share event and lands on the site
        const [opened] = await Promise.all([page.context().waitForEvent('page'), page.getByTestId('open-site').click()]);
        await expect(opened).toHaveURL(new RegExp(`${slug}\\.${BASE.replace('.', '\\.')}:${PORT}/(en|ar)`));
    });

    test('a taken subdomain shows suggestions and one tap fixes it', async ({ page }) => {
        const stamp = Date.now().toString(36);
        const list = JSON.parse(fs.readFileSync(path.join(dir, '.sites.json'), 'utf8'));
        const taken = list.sites.find((s) => s.status === 'live').slug;

        await page.goto(`${APP}/start`);
        await page.getByTestId('email').fill(`taken-${stamp}@example.com`);
        await page.getByTestId('send-code').click();
        const mail = await waitForMail(`taken-${stamp}@example.com`);
        await page.getByTestId('code').fill(mail.subject.match(/\d{6}/)[0]);
        await expect(page.locator('h1')).toHaveText('About you');
        await page.getByTestId('name').fill('Test Agent');
        await page.getByTestId('whatsapp').fill('+971501112233');
        await expect(page.getByTestId('whatsapp-ok')).toBeVisible();
        await page.getByTestId('next').click();

        await page.getByTestId('slug').fill(taken);
        await expect(page.getByTestId('slug-taken')).toBeVisible();
        const suggestion = page.locator('.app-chips .app-chip').first();
        await expect(suggestion).toBeVisible();
        const text = await suggestion.textContent();
        await suggestion.click();
        await expect(page.getByTestId('slug')).toHaveValue(text.trim());
        await expect(page.getByTestId('slug-available')).toBeVisible();
    });

    test('the wizard is Arabic and right-to-left with one tap', async ({ page }) => {
        await page.goto(`${APP}/start?lang=ar`);
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.getByTestId('send-code')).toHaveText('أرسل الرمز');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
        expect(overflow).toBeFalsy();
    });
});
