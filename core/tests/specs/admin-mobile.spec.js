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
    const data_panel = page.getByRole('heading', { name: 'Your data' }).locator('..');
    await expect(data_panel.locator('a[href$="/nb-admin/test-records/add"]')).toBeVisible();
    const data_layout = await data_panel.locator('ul').evaluate((list) => ({
      justify_content: getComputedStyle(list).justifyContent,
      item_flex_grow: getComputedStyle(list.querySelector('li')).flexGrow,
    }));
    expect(data_layout.justify_content).toBe('flex-start');
    expect(data_layout.item_flex_grow).toBe('0');
    await expectNoHorizontalOverflow(page);

    await expect(page.locator('#nb_nav_toggler')).toBeHidden();
    await expect(page.locator('#nb-bar [title="Site home"]')).toBeVisible();
    await expect(page.locator('#nb-bar [title="Admin dashboard"]')).toBeVisible();
    const nav_before = await page.locator('#nb-bar').boundingBox();
    await page.locator('#nb-bar button[title="Resources"]').click();
    const nav_after = await page.locator('#nb-bar').boundingBox();
    expect(nav_after?.height).toBe(nav_before?.height);
    expect(nav_after?.y).toBe(nav_before?.y);
    await expect(page.locator('#nb_mobile_resources_menu a[href$="/nb-admin/test-records"]')).toBeVisible();
    await expect(page.locator('#nb_mobile_resources_menu a[href$="/nb-admin/test-records/add"]')).toBeVisible();
    await expectNoHorizontalOverflow(page);
  });

  test('resource list and add form stay usable on a phone viewport', async ({ page }) => {
    await page.goto('/nb-admin/test-records');
    await expect(page.getByRole('searchbox')).toBeVisible();
    await expect(page.locator('#main a[href$="/nb-admin/test-records/add"]')).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.goto('/nb-admin/test-records/add');
    await expect(page.locator('[name=title]')).toBeVisible();
    await expect(page.locator('[name=score]')).toBeVisible();
    await expect(page.locator('form button[type=submit]')).toBeEnabled();
    await expectNoHorizontalOverflow(page);
  });
});
