import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { onboard, newestJson, waitForMail, APP, BASE, PORT, dir, shots, waSink } from './onboarding-flow.js';

// Acceptance test A2 (spec §21) on a phone-sized Chromium: landing → sign in with an emailed code →
// S2 → S3 → S4 → published in one flow, ≤ 6 typed fields, the site live on its own host afterwards,
// and the welcome WhatsApp handed to the notifier. Mail and WhatsApp go to file sinks (env.sh).

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
