import { test, expect } from '@playwright/test';

const CREDENTIALS = { email: 'test@nimbly.dev', password: 'testpass123' };

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', CREDENTIALS.email);
  await page.fill('[name=password]', CREDENTIALS.password);
  await page.click('[type=submit]');
  await page.waitForURL(url => !url.toString().includes('/login'));
}

test.beforeEach(async ({ page }) => {
  await login(page);
});

test('settings sections and dashboard navigation render through their real routes', async ({ page }) => {
  await page.goto('/nb-admin');
  await expect(page.locator('main a[href$="/nb-admin/navigation"]')).toHaveCount(0);

  await page.goto('/nb-admin/navigation');
  await expect(page.getByLabel('Slot')).toHaveValue('main');
  await expect(page.getByRole('tab', { name: 'EN' })).toHaveAttribute('aria-selected', 'true');
  await page.getByRole('tab', { name: 'NL' }).click();
  await expect(page).toHaveURL(/language=nl/);
  await expect(page.getByRole('tab', { name: 'NL' })).toHaveAttribute('aria-selected', 'true');
  await expect(page.locator('body')).not.toContainText('"#]');

  await page.goto('/nb-admin/settings');
  await expect(page.getByRole('heading', { name: 'Site', exact: true })).toBeVisible();
  await expect(page.getByRole('link', { name: 'General', exact: true })).toHaveAttribute('aria-current', 'page');
  await expect(page.getByLabel('Site name')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Users & access' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Manage navigation' })).toBeVisible();

  await page.getByRole('link', { name: 'Languages', exact: true }).click();
  await expect(page).toHaveURL(/section=languages/);
  await expect(page.getByLabel('Add language')).toBeVisible();
  await expect(page.getByText('English', { exact: true })).toBeVisible();

  await page.getByRole('link', { name: 'Page templates', exact: true }).click();
  await expect(page).toHaveURL(/section=page-templates/);
  await expect(page.getByText('Default page', { exact: true })).toBeVisible();
  await expect(page.getByText('Campaign page', { exact: true })).toBeVisible();
});

test('general settings warns before leaving with unsaved edits', async ({ page }) => {
  await page.goto('/nb-admin/settings?section=general');
  const input = page.getByLabel('Site name');
  await input.fill((await input.inputValue()) + ' draft');
  page.once('dialog', dialog => dialog.dismiss());
  await page.getByRole('link', { name: 'Languages', exact: true }).click();
  await expect(page).toHaveURL(/section=general/);
});

test.describe('narrow settings', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  test('tabs remain usable without horizontal page overflow', async ({ page }) => {
    await page.goto('/nb-admin/settings?section=page-templates');
    const dimensions = await page.evaluate(() => ({ width: window.innerWidth, scrollWidth: document.documentElement.scrollWidth }));
    expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.width + 1);
    await expect(page.getByRole('link', { name: 'Page templates', exact: true })).toBeVisible();
  });
});
