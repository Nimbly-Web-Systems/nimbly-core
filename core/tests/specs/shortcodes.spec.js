import { test, expect } from '@playwright/test';

test.beforeEach(async ({ page }) => {
  await page.goto('/login');
  await page.fill('[name=email]', 'test@nimbly.dev');
  await page.fill('[name=password]', 'testpass123');
  await page.click('[type=submit]');
  await page.waitForURL(url => !url.toString().includes('/login'));
});

test('[#get#] scalar variable', async ({ page }) => {
  await page.goto('/.test/shortcodes');
  await expect(page.locator('#get-scalar')).toHaveText('hello');
});

test('[#data#] + [#get#] resource field', async ({ page }) => {
  await page.goto('/.test/shortcodes');
  await expect(page.locator('#get-title')).toHaveText('Alpha record');
  await expect(page.locator('#get-score')).toHaveText('42');
});

test('[#get#] default fallback', async ({ page }) => {
  await page.goto('/.test/shortcodes');
  await expect(page.locator('#get-default')).toHaveText('fallback');
});

test('named date formats are locale-aware', async ({ page }) => {
  await page.goto('/.test/shortcodes');
  await expect(page.locator('#date-long-en')).toHaveText('June 10, 2026');
  await expect(page.locator('#date-long-nl')).toHaveText('10 juni 2026');
  await expect(page.locator('#fmt-date-long-nl')).toHaveText('10 juni 2026');
});

test('exact and invalid date formats remain deterministic', async ({ page }) => {
  await page.goto('/.test/shortcodes');
  await expect(page.locator('#date-exact')).toHaveText('2026/06/10');
  await expect(page.locator('#date-invalid')).toBeEmpty();
});
