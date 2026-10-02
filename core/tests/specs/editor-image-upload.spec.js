import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
const root = new URL('../../', import.meta.url);

async function setup(page, media = true, paste_html = false, mode = 'form') {
  await page.setContent('<form><div id="editor" contenteditable="true"><p>Before after</p></div><div id="other" contenteditable="true">Other field</div></form>');
  page.on('pageerror', error => { throw error; });
  await page.evaluate(() => { window.nb = { base_url: '' }; });
  for (const file of ['nb_upload.jsx', 'nb_edit.jsx', 'nb_bubble_editor.jsx']) {
    const source = await readFile(new URL('../js/' + file, root), 'utf8');
    await page.addScriptTag({ content: source.replace(/export default \w+;/, '') });
  }
  const state = await readFile(new URL('modules/forms/lib/build-form/edit-form-state.js', root), 'utf8');
  await page.addScriptTag({ content: state });
  await page.evaluate(({ media, paste_html, mode }) => {
    window.notices = [];
    window.nb = { base_url: '', text: {}, tw_breakpoints: { sm: 640, lg: 1024 }, max_upload_size: 50_000,
      notify: message => notices.push(message), upload: nb_upload, edit: nb_edit, bubble_editor: nb_bubble_editor };
    const ed = document.getElementById('editor');
    ed._nb_editor_options = { media, media_sizes: 'sm-90,lg-60' };
    ed._nb_mode = mode;
    ed._nb_inputs = 1;
    nb_edit.inputs = 1;
    nb_edit.enabled = true;
    nb_edit.editors = [ed];
    nb_bubble_editor.init(ed, { buttons: [], placeholder: '', as_form_field: mode === 'form', paste_html });
    window.changes = [];
    ed.addEventListener('nb:editor-change', e => changes.push(e.detail.value));
    ed.focus();
    const range = document.createRange();
    range.setStart(ed.querySelector('p').firstChild, 7);
    range.collapse(true);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
    window.requests = [];
    window.fetch = (_url, options) => new Promise(resolve => {
      const file = options.body.get('file');
      requests.push({ name: file.name, resolve: result => resolve({ json: async () => result }) });
    });
  }, { media, paste_html, mode });
}
async function send(page, kind = 'paste', names = ['first.png']) {
  await page.evaluate(({ kind, names }) => {
    const dt = new DataTransfer();
    names.forEach(name => dt.items.add(new File(['image bytes'], name, { type: 'image/png' })));
    const ed = document.getElementById('editor');
    const evt = kind === 'paste' ? new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true })
      : new DragEvent('drop', { dataTransfer: dt, bubbles: true, cancelable: true });
    ed.dispatchEvent(evt);
  }, { kind, names });
}
async function complete(page, index, uuid = 'cover', success = true) {
  await page.evaluate(({ index, uuid, success }) => requests[index].resolve({ success, message: 'Upload failed', files: { uuid, width: 800, height: 400 } }), { index, uuid, success });
}

test('drop preserves order and destination when upload completions and focus change', async ({ page }) => {
  await setup(page);
  await send(page, 'drop', ['first.png', 'second.png']);
  await page.locator('#other').focus();
  await complete(page, 1, 'second');
  await expect(page.locator('#editor img')).toHaveCount(0);
  await complete(page, 0, 'first');
  await expect(page.locator('#editor img')).toHaveCount(2);
  expect(await page.locator('#editor img').evaluateAll(imgs => imgs.map(img => img.getAttribute('src')))).toEqual(['/img/first/480w', '/img/second/480w']);
  await expect(page.locator('#other')).toBeFocused();
  await expect(page.locator('#other img')).toHaveCount(0);
  expect(await page.locator('#editor').innerHTML()).not.toContain('data:image');
  expect(await page.locator('#editor img').first().getAttribute('sizes')).toContain('(min-width: 1024px) 60vw');
  expect(await page.evaluate(() => changes.at(-1))).toContain('/img/first/480w');
});

test('clipboard file uses upload; pending inline and form saves keep their dirty state', async ({ page }) => {
  await setup(page, true, false, 'page');
  await send(page);
  const pending = await page.evaluate(() => {
    let writes = 0;
    nb_edit.save_resource = () => writes++;
    nb_edit.save();
    const state = nb_build_form_edit_state('projects', 'fixture');
    state._edit_form = document.querySelector('form');
    state.edit_submit();
    return { writes, inputs: nb_edit.inputs, dirty: document.getElementById('editor')._nb_inputs, busy: state.busy };
  });
  expect(pending).toEqual({ writes: 0, inputs: 1, dirty: 1, busy: false });
  await complete(page, 0);
  await expect(page.locator('#editor img')).toHaveCount(1);
  expect(await page.evaluate(() => nb_edit.pending_uploads.size)).toBe(0);
  expect(await page.evaluate(() => document.getElementById('editor')._nb_inputs)).toBe(2);
});

test('failed batch preserves existing content and never embeds images', async ({ page }) => {
  await setup(page);
  await send(page, 'paste', ['ok.png', 'bad.png']);
  await complete(page, 0);
  await complete(page, 1, 'bad', false);
  await expect(page.locator('[data-nb-upload-placeholder]')).toHaveCount(0);
  expect(await page.locator('#editor').innerHTML()).toBe('<p>Before after</p>');
  expect(await page.evaluate(() => nb_edit.pending_uploads.size)).toBe(0);
  expect(await page.evaluate(() => notices)).toContain('Image upload failed. Please try again.');
});

test('rich HTML embedded images upload once even with a clipboard file', async ({ page }) => {
  await setup(page, true, true);
  await page.evaluate(() => {
    const dt = new DataTransfer();
    dt.setData('text/html', '<p><b>Caption</b><img src="data:image/png;base64,aW1hZ2U=" alt="Existing description"></p>');
    dt.items.add(new File(['same image'], 'clipboard.png', { type: 'image/png' }));
    document.getElementById('editor').dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
  });
  expect(await page.evaluate(() => requests.length)).toBe(1);
  await complete(page, 0);
  await expect(page.locator('#editor img')).toHaveCount(1);
  await expect(page.locator('#editor img')).toHaveAttribute('alt', 'Existing description');
  await expect(page.locator('#editor b')).toHaveText('Caption');
  expect(await page.locator('#editor').innerHTML()).not.toContain('base64');
});

test('media-disabled fields reject image drop/paste and keep normal text paste', async ({ page }) => {
  await setup(page, false);
  await send(page, 'drop');
  await send(page, 'paste');
  expect(await page.evaluate(() => requests.length)).toBe(0);
  await expect(page.locator('#editor img')).toHaveCount(0);
  await page.evaluate(() => {
    const dt = new DataTransfer();dt.setData('text/plain', 'Normal text');
    document.getElementById('editor').dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
  });
  await expect(page.locator('#editor')).toContainText('Normal text');
});

test('oversized or non-image files are rejected before uploading', async ({ page }) => {
  await setup(page);
  await page.evaluate(() => {
    const dt = new DataTransfer();dt.items.add(new File([new Uint8Array(50_001)], 'large.png', { type: 'image/png' }));
    document.getElementById('editor').dispatchEvent(new DragEvent('drop', { dataTransfer: dt, bubbles: true, cancelable: true }));
  });
  expect(await page.evaluate(() => requests.length)).toBe(0);
  expect(await page.evaluate(() => nb_edit.pending_uploads.size)).toBe(0);
});
