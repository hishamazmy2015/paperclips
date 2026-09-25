import { defineConfig } from '@playwright/test';

// Mobile-viewport E2E against the real app (spec §19): Chromium maps *.example.test to the local
// server so tenant sites are opened by host, exactly as a phone would. Run `npm run e2e`
// (tests/E2E/prepare.sh seeds the database with generated sites first).
export default defineConfig({
    testDir: './tests/E2E',
    testIgnore: process.env.E2E_TIMING_RUNS ? [] : ['**/onboarding-timing.spec.js'],
    timeout: 30_000,
    fullyParallel: false,
    workers: 2,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL: 'http://127.0.0.1:8123',
        testIdAttribute: 'data-test',
        viewport: { width: 375, height: 667 }, // iPhone SE
        deviceScaleFactor: 2,
        isMobile: true,
        hasTouch: true,
        locale: 'en-GB',
        screenshot: 'only-on-failure',
        launchOptions: {
            args: ['--host-resolver-rules=MAP *.example.test 127.0.0.1, MAP example.test 127.0.0.1'],
            executablePath: process.env.PW_CHROMIUM || undefined,
        },
    },
    webServer: {
        command: 'bash tests/E2E/serve.sh',
        url: 'http://127.0.0.1:8123/up',
        reuseExistingServer: true,
        timeout: 60_000,
    },
});
