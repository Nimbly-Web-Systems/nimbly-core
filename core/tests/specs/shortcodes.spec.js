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
