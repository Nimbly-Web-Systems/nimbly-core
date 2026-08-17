import { test, expect } from '@playwright/test';

// nb_edit.jsx's dirty counter (nb_edit.inputs) exists to protect the
// front-end inline-content-editing feature: it's incremented by an 'input'
// listener attached to any [data-nb-edit] element NOT inside a <form>, and
// is only ever cleared by nb_edit.save() — the inline-editing save button.
// Admin build-form fields are deliberately excluded from it (see the
// as_form_field branch in init_editor), so it should never see any input on
// an admin page at all.
//
// It briefly did: the admin add/edit/view/import page headings were made
// inline-editable via the same .content system, sitting outside the
// <form>. Toggling "Edit" mode and touching that heading (even briefly)
// latched the counter for the rest of the page's life, since build-form's
// own save flow never calls nb_edit.save() to clear it — so a fully
// successful record save would redirect straight into a stale "unsaved
// changes" prompt, leaving the Save button stuck disabled.
//
// Fixed two ways: the admin page headings are no longer inline-editable
// (they're already customizable via the resource's own name in .meta plus
// the [#text#] i18n system — there was no real use case for editing them
// inline on top of that), and nb_edit.on_beforeunload() also skips the
// warning entirely on admin pages as a safety net, since the counter can
// never legitimately reflect the record being worked on there.

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', 'test@nimbly.dev');
  await page.fill('[name=password]', 'testpass123');
  await page.click('[type=submit]');
  await page.waitForURL((url) => !url.toString().includes('/login'));
}

test('the admin add-form heading is not wired into inline-content editing', async ({ page }) => {
  await login(page);
  await page.goto('/nb-admin/test-records/add');

  await expect(page.locator('h1[data-nb-edit]')).toHaveCount(0);
});

test('a stray latched dirty counter still does not block leaving an admin page', async ({ page }) => {
  await login(page);
  await page.goto('/nb-admin/test-records/add');
  await page.evaluate(() => {
    window.nb.edit.inputs = 1;
  });

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
