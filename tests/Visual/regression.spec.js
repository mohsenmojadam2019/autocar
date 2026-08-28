const { test, expect } = require('@playwright/test');

async function assertHealthyPage(page, path, testInfo) {
  const consoleErrors = [];
  page.on('console', message => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });

  const response = await page.goto(path, { waitUntil: 'networkidle' });
  expect(response && response.ok()).toBeTruthy();
  await expect(page.locator('body')).toBeVisible();

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow).toBeLessThanOrEqual(2);
  expect(consoleErrors).toEqual([]);

  await page.screenshot({
    path: testInfo.outputPath(`${testInfo.project.name}-${path === '/' ? 'home' : path.replaceAll('/', '-')}.png`),
    fullPage: true
  });
}

test('storefront visual smoke', async ({ page }, testInfo) => {
  await assertHealthyPage(page, '/', testInfo);
  await assertHealthyPage(page, '/search', testInfo);
  await assertHealthyPage(page, '/login', testInfo);
});

test('admin visual smoke', async ({ page }, testInfo) => {
  await page.goto('/login');
  await page.locator('input[name="login"]').fill('admin@autocar.local');
  await page.locator('input[name="password"]').fill('password');
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.locator('form[action*="login"] button[type="submit"], form[action*="login"] button').first().click()
  ]);
  await assertHealthyPage(page, '/admin', testInfo);
  await assertHealthyPage(page, '/admin/products', testInfo);
  await assertHealthyPage(page, '/admin/orders', testInfo);
});
