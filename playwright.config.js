import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Visual',
    timeout: 30000,
    retries: 1,
    use: {
        baseURL: 'http://127.0.0.1:8000',
        locale: 'fa-IR',
        timezoneId: 'Asia/Tehran',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop-chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 1000 } } },
        { name: 'mobile-chromium', use: { ...devices['Pixel 7'] } },
    ],
});
