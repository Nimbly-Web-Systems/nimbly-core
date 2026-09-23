import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';

const root = new URL('../../', import.meta.url);

async function translation_form(page, english = '') {
  page.on('pageerror', (error) => { throw error; });
  await page.setContent(`<form x-data="translation_form" :data-lang="lang">
    <button type="button" id="nl" @click="switch_language('nl')">NL</button>
    <button type="button" id="en" @click="switch_language('en')">EN</button>
    <button type="button" id="translate" @click="ai('body', lang)" :disabled="busy || !translation_field_empty('body', lang)">Body</button>
    <button type="button" id="translate-all" @click="ai_all(lang)" :disabled="busy">Translate all</button>
    <div data-nb-edit="body" data-nb-edit-i18n="true"></div>
    <button type="button" id="save-button" @click="save()" :disabled="busy">Save</button>
  </form>`);
  await page.addScriptTag({ content: await readFile(new URL('../node_modules/medium-editor/dist/js/medium-editor.js', root), 'utf8') });
  const editor_script = await readFile(new URL('../js/nb_edit.jsx', root), 'utf8');
  await page.addScriptTag({ content: editor_script.replace('export default nb_edit;', '') });
  await page.addScriptTag({ content: await readFile(new URL('modules/forms/lib/build-form/edit-form-state.js', root), 'utf8') });
  await page.evaluate((english) => {
    window.nb = {
      base_url: '', edit: window.nb_edit,
      text: { medium_editor_placeholder: 'Body', record_updated: 'Saved' },
      notify: () => {},
      api: {
        post: async (_url, payload) => {
          window.translation_request = payload;
          await new Promise((resolve) => { window.finish_translation = resolve; });
          return { success: true, completion: '<p>English body</p>', completions: { body: '<p>English body</p>' } };
        },
        put: async (_url, payload) => {
          window.saved_record = JSON.parse(JSON.stringify(payload));
          return { success: true };
        },
      },
    };
    document.addEventListener('alpine:init', () => {
      Alpine.store('form_language', { current: 'nl' });
      Alpine.data('translation_form', () => ({
        ...nb_build_form_edit_state('articles', 'fixture', {
          initial_lang: 'nl', translation_mode: 'field',
          ai_record_action_fields: ['body'], translation_languages: ['nl', 'en'],
          record: { body: { nl: '<p>Nederlandse inhoud</p>', en: english } },
        }),
        init() { this.init_edit_state(); },
      }));
    });
  }, english);
  await page.addScriptTag({ content: await readFile(new URL('../node_modules/alpinejs/dist/cdn.js', root), 'utf8') });
  await expect(page.locator('[data-nb-edit]')).toHaveText('Nederlandse inhoud');
  await page.locator('#en').click();
}

test('field translation refreshes the visible body and saves without switching tabs', async ({ page }) => {
  await translation_form(page);
  await page.locator('#translate').click();
  await expect(page.locator('#save-button')).toBeDisabled();
  await page.evaluate(() => window.finish_translation());
  await expect(page.locator('[data-nb-edit]')).toHaveText('English body');
  await expect(page.locator('#save-button')).toBeEnabled();
  await page.locator('#save-button').click();
  expect(await page.evaluate(() => window.saved_record.body)).toEqual({ nl: '<p>Nederlandse inhoud</p>', en: '<p>English body</p>' });
});

test('bulk translation replaces empty rich-text markup', async ({ page }) => {
  await translation_form(page, '<p><br></p>');
  await page.locator('#translate-all').click();
  await page.evaluate(() => window.finish_translation());
  await expect(page.locator('[data-nb-edit]')).toHaveText('English body');
});

test('switching tabs during translation preserves edits and the selected language', async ({ page }) => {
  await translation_form(page);
  await page.locator('#translate-all').click();
  await page.locator('#nl').click();
  await page.locator('[data-nb-edit]').fill('Gewijzigde inhoud');
  await page.evaluate(() => window.finish_translation());
  await expect(page.locator('#translate-all')).toBeEnabled();
  await expect(page.locator('[data-nb-edit]')).toHaveText('Gewijzigde inhoud');
  await page.locator('#en').click();
  await expect(page.locator('[data-nb-edit]')).toHaveText('English body');
  await page.locator('#nl').click();
  await expect(page.locator('[data-nb-edit]')).toHaveText('Gewijzigde inhoud');
});

test('a translation error restores the buttons and preserves the source', async ({ page }) => {
  await translation_form(page);
  await page.evaluate(() => {
    nb.api.post = async () => { throw new Error('Translation unavailable'); };
  });
  await page.locator('#translate').click();
  await expect(page.locator('#translate')).toBeEnabled();
  await expect(page.locator('#save-button')).toBeEnabled();
  await page.locator('#nl').click();
  await expect(page.locator('[data-nb-edit]')).toHaveText('Nederlandse inhoud');
});

test('media insertion updates a form editor at its caret', async ({ page }) => {
  await page.setContent('<form><div data-nb-edit="body"><p>Before after</p></div></form>');
  await page.addScriptTag({ content: await readFile(new URL('../node_modules/medium-editor/dist/js/medium-editor.js', root), 'utf8') });
  const editor_script = await readFile(new URL('../js/nb_edit.jsx', root), 'utf8');
  await page.addScriptTag({ content: editor_script.replace('export default nb_edit;', '') });

  const result = await page.evaluate(() => {
    window.nb = {
      text: { medium_editor_placeholder: 'Body' },
    };
    const editor = document.querySelector('[data-nb-edit]');
    nb_edit.init_editor(editor, true);
    let changed_value = null;
    editor.addEventListener('nb:editor-change', (event) => {
      changed_value = event.detail.value;
    });

    const text = editor.querySelector('p').firstChild;
    const range = document.createRange();
    range.setStart(text, 7);
    range.collapse(true);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    nb_edit.active_editor = editor;
    nb_edit.insert_html('<img src="/img/example/480w" alt="Example">');

    return { html: editor.innerHTML, changed_value };
  });

  expect(result.html).toContain('Before <img src="/img/example/480w" alt="Example">after');
  expect(result.changed_value).toBe(result.html);
});
