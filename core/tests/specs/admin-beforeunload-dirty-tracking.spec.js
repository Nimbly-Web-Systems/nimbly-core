import { test, expect } from '@playwright/test';

// nb_edit.jsx's dirty counter (nb_edit.inputs) exists to protect the
// front-end inline-content-editing feature: it's incremented by an 'input'
// listener attached to any [data-nb-edit] element NOT inside a <form>, and
// is only ever cleared by nb_edit.save() — the inline-editing save button.
//
// Admin pages render their own inline-editable page chrome (e.g. the "Add
// <Resource>" heading, wired through the same .content system) outside the
// build-form <form> element, so it gets that same 'input' listener. If an
// editor toggles "Edit" mode (the same control used on public pages) while
// on an admin page and their cursor ever touches that chrome text, the
// counter latches — and build-form's own save flow never calls
// nb_edit.save(), so it never clears. The next successful record save then
// redirects via window.location.href, and the browser's beforeunload
// handler blocks it with an "unsaved changes" prompt over data that was
// never actually at risk, leaving the Save button stuck disabled.
//
// Fix: nb_edit.on_beforeunload() skips the warning entirely on admin pages,
// since admin build-form fields are deliberately excluded from this same
// counter already (see the as_form_field branch in init_editor) — so on an
// admin page the counter can only reflect incidental page-chrome edits,
// never the record actually being worked on.

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', 'test@nimbly.dev');
  await page.fill('[name=password]', 'testpass123');
  await page.click('[type=submit]');
  await page.waitForURL((url) => !url.toString().includes('/login'));
}

test('a latched dirty counter from page-chrome editing does not block leaving an admin page', async ({ page }) => {
  await page.setViewportSize({ width: 1400, height: 900 });
  await login(page);
  await page.goto('/nb-admin/test-records/add');

  // Simulate an editor toggling inline "Edit" mode and touching the page's
  // own heading — chrome text, not form data.
  await page.locator('[data-nb-edit-toggle]:visible').first().click();
  const heading = page.locator('h1[data-nb-edit]').first();
  await expect(heading).toBeVisible();
  await heading.click();
  await page.evaluate(() => document.execCommand('insertText', false, 'x'));
  await page.locator('[data-nb-edit-toggle]:visible').first().click();

  const inputs = await page.evaluate(() => window.nb.edit.inputs);
  expect(inputs).toBeGreaterThanOrEqual(1);

  const result = await page.evaluate(() => window.nb.edit.on_beforeunload({}));
  expect(result).toBeUndefined();
});

test('the same latched dirty counter still warns off of admin pages', async ({ page }) => {
  await login(page);
  await page.goto('/');
  await page.evaluate(() => {
    window.nb.edit.inputs = 1;
  });

  const result = await page.evaluate(() => window.nb.edit.on_beforeunload({}));
  expect(result).toBeDefined();
});
