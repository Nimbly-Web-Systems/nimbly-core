import { test, expect } from '@playwright/test';

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', 'test@nimbly.dev');
  await page.fill('[name=password]', 'testpass123');
  await page.click('[type=submit]');
  await page.waitForURL(url => !url.toString().includes('/login'));
}

test.describe('admin edit — i18n and group fields (test-i18n-records)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('edit an i18n field, switch language tabs, save', async ({ page }) => {
    await page.goto('/nb-admin/test-i18n-records/test-i18n-001');

    const en_tab = page.getByRole('button', { name: 'en', exact: true });
    const nl_tab = page.getByRole('button', { name: 'nl', exact: true });
    await expect(en_tab).toBeVisible();
    await expect(nl_tab).toBeVisible();

    await page.fill('[name=title]', 'Playwright English title');
    await nl_tab.click();
    await page.waitForTimeout(500);

    await expect(page.locator('[name=title]')).toHaveValue('Nederlandse titel');

    // Switching tabs must not save. The English edit should still be there,
    // held in local form state, once we switch back.
    await en_tab.click();
    await page.waitForTimeout(500);
    await expect(page.locator('[name=title]')).toHaveValue('Playwright English title');

    const submit = page.locator('form button[type=submit]').first();
    await submit.click();
    await page.waitForURL(/\/nb-admin\/test-i18n-records$/);

    await page.goto('/nb-admin/test-i18n-records/test-i18n-001');
    await expect(page.locator('body')).toContainText('Playwright English title');
  });

  test('add a group field item and save', async ({ page }) => {
    await page.goto('/nb-admin/test-i18n-records/test-i18n-001');

    const add_button = page.getByRole('button', { name: 'Add', exact: true });
    await add_button.click();

    const group_items = page.locator('div[x-data^="nb_group_field"] > div.mb-3.rounded');
    await expect(group_items).toHaveCount(2);
    await expect(group_items.last().locator('input').first()).toBeVisible();

    const submit = page.locator('form button[type=submit]').first();
    await submit.click();
    // A successful save redirects back to the resource list.
    await page.waitForURL(/\/nb-admin\/test-i18n-records$/);

    await page.goto('/nb-admin/test-i18n-records/test-i18n-001');
    const rows = page.locator('script#nb-group-value-items');
    const value = await rows.textContent();
    expect(JSON.parse(value).length).toBeGreaterThan(1);
  });

  test('delete the record', async ({ page }) => {
    await page.goto('/nb-admin/test-i18n-records/test-i18n-001');

    page.on('dialog', dialog => dialog.accept());
    await page.getByRole('button', { name: 'Delete' }).click();

    await page.waitForURL(/\/nb-admin\/test-i18n-records$/);
    await expect(page.locator('body')).not.toContainText('test-i18n-001');
  });
});
