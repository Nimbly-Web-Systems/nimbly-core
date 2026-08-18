import { test, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fixture = path.join(__dirname, '..', 'fixtures', 'import-document-sample.docx');

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', 'test@nimbly.dev');
  await page.fill('[name=password]', 'testpass123');
  await page.click('[type=submit]');
  await page.waitForURL((url) => !url.toString().includes('/login'));
}

test.describe('admin add form — import from document (test-records)', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('add form offers the import action; edit and view forms do not', async ({ page }) => {
    await page.goto('/nb-admin/test-records/add');
    await expect(page.getByRole('heading', { name: 'Import from document' })).toBeVisible();

    await page.goto('/nb-admin/test-records/test-001');
    await expect(page.getByRole('heading', { name: 'Import from document' })).toHaveCount(0);

    // The read-only view route never runs build_form(), so it has no
    // _bf_uuid either — same as the add page. Regression coverage for that
    // scope check wrongly bucketing "view" in with "add".
    await page.goto('/nb-admin/test-records/test-001?view=1');
    await expect(page.getByRole('heading', { name: 'Import from document' })).toHaveCount(0);
  });

  test('uploading a document fills empty fields and leaves typed ones alone', async ({ page }) => {
    const console_errors = [];
    page.on('pageerror', (err) => console_errors.push(err.message));

    await page.goto('/nb-admin/test-records/add');

    // A field the editor already typed into must survive the import untouched.
    await page.fill('[name=title]', 'Already typed title');

    await page.locator('input[type=file][accept=".docx"]').setInputFiles(fixture);
    await expect(page.locator('text=Filled in:')).toBeVisible({ timeout: 15000 });

    await expect(page.locator('[name=title]')).toHaveValue('Already typed title');
    await expect(page.locator('[name=notes]')).not.toHaveValue('');

    expect(console_errors).toEqual([]);
  });
});
