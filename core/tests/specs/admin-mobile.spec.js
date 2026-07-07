import { test, expect } from '@playwright/test';

const CREDENTIALS = { email: 'test@nimbly.dev', password: 'testpass123' };

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', CREDENTIALS.email);
  await page.fill('[name=password]', CREDENTIALS.password);
  await page.click('[type=submit]');
  await page.waitForURL(url => !url.toString().includes('/login'));
}

async function expectNoHorizontalOverflow(page) {
  const overflow = await page.evaluate(() => ({
    width: window.innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
  }));
  expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.width + 1);
}

test.describe('mobile admin', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('dashboard mobile nav opens resource sheet without horizontal overflow', async ({ page }) => {
    await page.goto('/nb-admin/');
    await expect(page.locator('#nb-bar')).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.locator('#nb_nav_toggler').click();
    await expect(page.getByRole('button', { name: /resources/i })).toBeVisible();
    await expect(page.locator('#nb-bar a[href$="/nb-admin/test-records"]')).toBeVisible();
    await expectNoHorizontalOverflow(page);
  });

  test('resource list and add form stay usable on a phone viewport', async ({ page }) => {
    await page.goto('/nb-admin/test-records');
    await expect(page.getByRole('searchbox')).toBeVisible();
    await expect(page.locator('a[href$="/nb-admin/test-records/add"]')).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.goto('/nb-admin/test-records/add');
    await expect(page.locator('[name=title]')).toBeVisible();
    await expect(page.locator('[name=score]')).toBeVisible();
    await expect(page.locator('form button[type=submit]')).toBeEnabled();
    await expectNoHorizontalOverflow(page);
  });
});
