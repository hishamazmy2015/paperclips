// The onboarding flow shared by onboarding.spec.js (assertions) and onboarding-timing.spec.js (median).
import { expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

export const PORT = process.env.E2E_PORT || '8123';
export const BASE = process.env.PLATFORM_BASE_DOMAIN || 'example.test';
export const APP = `http://app.${BASE}:${PORT}`;
export const dir = path.dirname(new URL(import.meta.url).pathname);
const appRoot = path.resolve(dir, '../..');
export const mailSink = path.join(appRoot, 'storage/app/private/mail-sink');
export const waSink = path.join(appRoot, 'storage/app/private/whatsapp-sink');
export const shots = path.join(dir, 'screenshots');
fs.mkdirSync(shots, { recursive: true });

export function newestJson(sinkDir, predicate = () => true) {
    if (!fs.existsSync(sinkDir)) return null;
    const files = fs.readdirSync(sinkDir).filter((f) => f.endsWith('.json')).sort();
    for (const file of files.reverse()) {
        const data = JSON.parse(fs.readFileSync(path.join(sinkDir, file), 'utf8'));
        if (predicate(data)) return data;
    }
    return null;
}

export async function waitForMail(to) {
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

