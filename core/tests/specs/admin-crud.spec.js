import { test, expect } from '@playwright/test';

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', 'test@nimbly.dev');
  await page.fill('[name=password]', 'testpass123');
  await page.click('[type=submit]');
  await page.waitForURL(url => !url.toString().includes('/login'));
}

test.describe('admin CRUD — test-records', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('create a record', async ({ page }) => {
    await page.goto('/nb-admin/test-records/add');
    const submit = page.locator('form button[type=submit]');
    await expect(submit).toBeEnabled();

    await page.fill('[name=title]', 'Playwright record');
    await page.fill('[name=score]', '99');
    await page.fill('[name=notes]', 'Created by Playwright');
    await submit.click();

    await page.waitForURL(/\/nb-admin\/test-records$/);
    await expect(page.locator('body')).toContainText('Playwright record');
  });

  test('edit the created record', async ({ page }) => {
    // find the edit link for the record we created
    await page.goto('/nb-admin/test-records');
    await expect(page.locator('body')).toContainText('Playwright record', { timeout: 10_000 });
    // get the edit URL from the row
    const row = page.locator('tr', { has: page.locator('text=Playwright record') });
    const edit_href = await row.locator('a[title="Edit"]').getAttribute('href');
    await page.goto(edit_href);

    const submit = page.locator('form button[type=submit]').first();
    await expect(submit).toBeEnabled();
    await page.fill('[name=title]', 'Playwright record edited');
    await submit.click();

    await page.waitForURL(/\/nb-admin\/test-records$/);
    await expect(page.locator('body')).toContainText('Playwright record edited');
  });

  test('delete the created record', async ({ page }) => {
    await page.goto('/nb-admin/test-records');
    await expect(page.locator('body')).toContainText('Playwright record', { timeout: 10_000 });

    page.on('dialog', dialog => dialog.accept());
    const row = page.locator('tr', { has: page.getByText('Playwright record edited', { exact: true }) });
    await row.locator('button[title=Delete]').click();

    await expect(page.locator('body')).not.toContainText('Playwright record', { timeout: 10_000 });
  });
});
