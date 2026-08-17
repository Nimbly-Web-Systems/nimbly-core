import { test, expect } from '@playwright/test';

// A save's flash message (nb.system_message, POST /api/v1/session) is a
// nice-to-have for the next page — if it hangs or fails, the record is
// already saved (the earlier create/update call already succeeded), so the
// redirect must still happen. Regression coverage for a bug where the
// success handler awaited that flash-message call with no .catch(),
// leaving the editor stranded on a page whose Save button never
// re-enabled even though their work had already been persisted.

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', 'test@nimbly.dev');
  await page.fill('[name=password]', 'testpass123');
  await page.click('[type=submit]');
  await page.waitForURL((url) => !url.toString().includes('/login'));
}

test.describe('save redirect survives a failing flash message (test-records)', () => {
  test.beforeEach(async ({ page }) => {
    await page.route('**/api/v1/session', (route) => route.abort('failed'));
    await login(page);
  });

  test('add form still redirects after a successful create', async ({ page }) => {
    // Short and unique — the list table truncates long titles with an
    // ellipsis, so a longer label would never appear verbatim in the DOM.
    const title = 'RRT ' + Date.now();
    await page.goto('/nb-admin/test-records/add');
    await page.fill('[name=title]', title);
    await page.fill('[name=notes]', 'test notes');

    const submit = page.locator('form button[type=submit]').first();
    await submit.click();

    await page.waitForURL(/\/nb-admin\/test-records$/, { timeout: 8000 });

    // Clean up the record this test created so it doesn't pollute the
    // fixed seed-record count other specs (e.g. auth.spec.js) assert on.
    // The list page makes its own /api/v1/session calls (unrelated to the
    // save flow under test), so drop the abort route now that the save
    // itself has already redirected.
    await page.unroute('**/api/v1/session');
    await expect(page.locator('body')).toContainText(title, { timeout: 10_000 });
    page.on('dialog', (dialog) => dialog.accept());
    const row = page.locator('tr', { has: page.getByText(title, { exact: true }) });
    await row.locator('button[title=Delete]').click();
    await expect(page.locator('body')).not.toContainText(title, { timeout: 10_000 });
  });

  test('edit form still redirects after a successful update', async ({ page }) => {
    await page.goto('/nb-admin/test-records/test-001');
    await page.fill('[name=notes]', 'edited notes ' + Date.now());

    const submit = page.locator('form button[type=submit]').first();
    await submit.click();

    await page.waitForURL(/\/nb-admin\/test-records$/, { timeout: 8000 });
  });
});
