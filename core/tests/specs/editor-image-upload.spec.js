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
    window.nb = { base_url: '', text: {}, tw_breakpoints: { sm: 640, lg: 1024 }, max_upload_size: '50000',
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
    // controllable XHR: tests drive progress, responses and network failures
    window.requests = [];
    window.XMLHttpRequest = class {
      constructor() { this.listeners = {}; this.upload = { listeners: {}, addEventListener: (t, f) => { this.upload.listeners[t] = f; } }; }
      open() {}
      addEventListener(type, fn) { this.listeners[type] = fn; }
      send(body) {
        const xhr = this;
        requests.push({
          name: body.get('file').name,
          aborted: false,
          progress: (loaded, total) => xhr.upload.listeners.progress?.({ lengthComputable: true, loaded, total }),
          resolve: (result, status = 201) => { xhr.status = status; xhr.responseText = typeof result === 'string' ? result : JSON.stringify(result); xhr.listeners.load(); },
          reject: () => xhr.listeners.error(),
        });
        this.request = requests.at(-1);
      }
      abort() { this.request.aborted = true; this.listeners.abort(); }
    };
  }, { media, paste_html, mode });
}
async function send(page, kind = 'paste', names = ['first.png'], type = 'image/png') {
  await page.evaluate(({ kind, names, type }) => {
    const dt = new DataTransfer();
    names.forEach(name => dt.items.add(new File(['image bytes'], name, { type })));
    const ed = document.getElementById('editor');
    const evt = kind === 'paste' ? new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true })
      : new DragEvent('drop', { dataTransfer: dt, bubbles: true, cancelable: true });
    ed.dispatchEvent(evt);
  }, { kind, names, type });
}
async function complete(page, index, uuid = 'cover', success = true) {
  await page.evaluate(({ index, uuid, success }) => requests[index].resolve({ success, message: 'Upload failed', files: { uuid, width: 800, height: 400 } }), { index, uuid, success });
}
const placeholders = page => page.locator('#editor [data-nb-upload-placeholder]');

test('drop preserves order and destination when upload completions and focus change', async ({ page }) => {
  await setup(page);
  await send(page, 'drop', ['first.png', 'second.png']);
  await page.locator('#other').focus();
  await complete(page, 1, 'second');
  await expect(page.locator('#editor img:not(.nb-upload-preview)')).toHaveCount(1);
  await complete(page, 0, 'first');
  await expect(placeholders(page)).toHaveCount(0);
  expect(await page.locator('#editor img').evaluateAll(imgs => imgs.map(img => img.getAttribute('src')))).toEqual(['/img/first/480w', '/img/second/480w']);
  await expect(page.locator('#other')).toBeFocused();
  await expect(page.locator('#other img')).toHaveCount(0);
  expect(await page.locator('#editor').innerHTML()).not.toContain('data:image');
  expect(await page.locator('#editor').innerHTML()).not.toContain('blob:');
  expect(await page.locator('#editor img').first().getAttribute('sizes')).toContain('(min-width: 1024px) 60vw');
  expect(await page.evaluate(() => changes.at(-1))).toContain('/img/first/480w');
});

test('placeholder shows a local preview, progress, then processing', async ({ page }) => {
  await setup(page);
  await send(page, 'drop');
  const ph = placeholders(page);
  await expect(ph).toHaveCount(1);
  expect(await ph.locator('.nb-upload-preview').getAttribute('src')).toMatch(/^blob:/);
  await page.evaluate(() => requests[0].progress(42, 100));
  await expect(ph).toHaveAttribute('data-state', 'uploading');
  await expect(ph.locator('[role=progressbar]')).toHaveAttribute('aria-valuenow', '42');
  await expect(ph.locator('.nb-upload-label')).toHaveText('Uploading 42%');
  await page.evaluate(() => requests[0].progress(100, 100));
  await expect(ph).toHaveAttribute('data-state', 'processing');
  await complete(page, 0);
  await expect(ph).toHaveCount(0);
  await expect(page.locator('#editor img')).toHaveCount(1);
  expect(await page.evaluate(() => notices)).toEqual([]);
});

test('clipboard file uses upload; saves wait for uploads and then run', async ({ page }) => {
  await setup(page, true, false, 'page');
  await send(page);
  const pending = await page.evaluate(() => {
    window.writes = [];
    nb_edit.save_resource = ed => writes.push(nb_edit.editor_html(ed));
    nb_edit.save();
    nb_edit.save();
    return { writes: writes.length, inputs: nb_edit.inputs, dirty: document.getElementById('editor')._nb_inputs };
  });
  expect(pending).toEqual({ writes: 0, inputs: 1, dirty: 1 });
  expect(await page.evaluate(() => notices)).toEqual(['Saving as soon as the images are uploaded…']);
  await complete(page, 0);
  await expect(page.locator('#editor img')).toHaveCount(1);
  expect(await page.evaluate(() => nb_edit.pending_uploads.size)).toBe(0);
  const writes = await page.evaluate(() => writes);
  expect(writes).toHaveLength(1);
  expect(writes[0]).toContain('/img/cover/480w');
});

test('form submit waits for uploads', async ({ page }) => {
  await setup(page);
  await send(page);
  const busy = await page.evaluate(() => {
    const state = nb_build_form_edit_state('projects', 'fixture');
    state._edit_form = document.querySelector('form');
    window.submitted = 0;
    state.edit_submit();
    const busy = state.busy;
    state.edit_submit = () => submitted++; // the deferred retry lands here
    return busy;
  });
  expect(busy).toBe(false);
  await complete(page, 0);
  await expect.poll(() => page.evaluate(() => submitted)).toBe(1);
});

test('failed upload keeps its slot with retry, and retry succeeds', async ({ page }) => {
  await setup(page);
  await send(page, 'paste', ['ok.png', 'bad.png']);
  await complete(page, 0, 'ok');
  await complete(page, 1, 'bad', false);
  const ph = placeholders(page);
  await expect(ph).toHaveCount(1);
  await expect(ph).toHaveAttribute('data-state', 'error');
  await expect(ph.locator('.nb-upload-label')).toHaveText('Image upload failed. Please try again.');
  expect(await page.evaluate(() => nb_edit.pending_uploads.size)).toBe(0);
  expect(await page.evaluate(() => changes.at(-1))).not.toContain('nb-upload');
  await ph.locator('[data-upload-action=retry]').click();
  await expect(ph).toHaveAttribute('data-state', 'uploading');
  await complete(page, 2, 'bad-again');
  await expect(ph).toHaveCount(0);
  expect(await page.locator('#editor img').evaluateAll(imgs => imgs.map(img => img.getAttribute('src')))).toEqual(['/img/ok/480w', '/img/bad-again/480w']);
});

test('network failure and HTML error pages show an error state; remove cleans up', async ({ page }) => {
  await setup(page);
  await send(page, 'paste', ['offline.png', 'proxy.png']);
  await page.evaluate(() => requests[0].reject());
  await page.evaluate(() => requests[1].resolve('<html>413 Request Entity Too Large</html>', 413));
  await expect(placeholders(page).locator('.nb-upload-label')).toHaveText([
    'Image upload failed. Please try again.', 'This image is larger than the upload limit.']);
  await placeholders(page).first().locator('[data-upload-action=remove]').click();
  await placeholders(page).first().locator('[data-upload-action=remove]').click();
  await expect(placeholders(page)).toHaveCount(0);
  expect(await page.locator('#editor').innerHTML()).toBe('<p>Before after</p>');
});

test('cancel aborts the upload and removes the placeholder', async ({ page }) => {
  await setup(page);
  await send(page, 'drop');
  await placeholders(page).locator('[data-upload-action=cancel]').click();
  await expect(placeholders(page)).toHaveCount(0);
  expect(await page.evaluate(() => requests[0].aborted)).toBe(true);
  expect(await page.evaluate(() => nb_edit.pending_uploads.size)).toBe(0);
  expect(await page.locator('#editor').getAttribute('aria-busy')).toBeNull();
});

test('at most three uploads run at once', async ({ page }) => {
  await setup(page);
  await send(page, 'drop', ['1.png', '2.png', '3.png', '4.png', '5.png']);
  expect(await page.evaluate(() => requests.length)).toBe(3);
  await expect(placeholders(page).nth(4)).toHaveAttribute('data-state', 'queued');
  await complete(page, 0, 'one');
  await expect.poll(() => page.evaluate(() => requests.length)).toBe(4);
});

test('typing or switching language during an upload never stores the placeholder', async ({ page }) => {
  await setup(page);
  await send(page, 'drop');
  await page.locator('#editor').press('End');
  await page.keyboard.type('x');
  expect(await page.evaluate(() => changes.at(-1))).not.toContain('nb-upload');
  const switched = await page.evaluate(() => {
    const state = nb_build_form_edit_state('projects', 'fixture');
    state._edit_form = document.querySelector('form');
    state.lang = 'en';
    state.$store = { form_language: { current: 'en' } };
    state.switch_language('nl');
    return state.lang;
  });
  expect(switched).toBe('en');
  await expect(placeholders(page)).toHaveCount(1);
});

test('rich HTML shows text immediately and uploads embedded images in place', async ({ page }) => {
  await setup(page, true, true);
  await page.evaluate(() => {
    const dt = new DataTransfer();
    dt.setData('text/html', '<p><b>Caption</b><img src="data:image/png;base64,aW1hZ2U=" alt="Existing description"></p>');
    dt.items.add(new File(['same image'], 'clipboard.png', { type: 'image/png' }));
    document.getElementById('editor').dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
  });
  expect(await page.evaluate(() => requests.length)).toBe(1);
  await expect(page.locator('#editor b')).toHaveText('Caption');
  await expect(placeholders(page)).toHaveCount(1);
  await complete(page, 0);
  await expect(placeholders(page)).toHaveCount(0);
  await expect(page.locator('#editor img')).toHaveCount(1);
  await expect(page.locator('#editor img')).toHaveAttribute('alt', 'Existing description');
  expect(await page.locator('#editor').innerHTML()).not.toContain('base64');
});

test('media-disabled fields reject image drop/paste and keep normal text paste', async ({ page }) => {
  await setup(page, false);
  await send(page, 'drop');
  await send(page, 'paste');
  expect(await page.evaluate(() => requests.length)).toBe(0);
  await expect(page.locator('#editor img')).toHaveCount(0);
  const effect = await page.evaluate(() => {
    const dt = new DataTransfer();
    dt.items.add(new File(['x'], 'a.png', { type: 'image/png' }));
    const evt = new DragEvent('dragover', { dataTransfer: dt, bubbles: true, cancelable: true });
    document.getElementById('editor').dispatchEvent(evt);
    return dt.dropEffect;
  });
  expect(effect).toBe('none');
  await page.evaluate(() => {
    const dt = new DataTransfer();dt.setData('text/plain', 'Normal text');
    document.getElementById('editor').dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
  });
  await expect(page.locator('#editor')).toContainText('Normal text');
});

test('dragging files highlights media editors until they leave', async ({ page }) => {
  await setup(page);
  const state = await page.evaluate(() => {
    const ed = document.getElementById('editor');
    const dt = new DataTransfer();
    dt.items.add(new File(['x'], 'a.png', { type: 'image/png' }));
    const fire = (type, target = ed) => target.dispatchEvent(new DragEvent(type, { dataTransfer: dt, bubbles: true, cancelable: true }));
    fire('dragenter');
    fire('dragenter', ed.querySelector('p'));
    fire('dragleave', ed.querySelector('p'));
    const during = ed.hasAttribute('data-nb-drop-active');
    fire('dragleave');
    return { during, after: ed.hasAttribute('data-nb-drop-active') };
  });
  expect(state).toEqual({ during: true, after: false });
});

test('invalid files are skipped individually with a clear reason', async ({ page }) => {
  await setup(page);
  await page.evaluate(() => {
    const dt = new DataTransfer();
    dt.items.add(new File([new Uint8Array(50_001)], 'large.png', { type: 'image/png' }));
    dt.items.add(new File(['x'], 'IMG_0001.HEIC', { type: 'image/heic' }));
    dt.items.add(new File(['x'], 'notes.pdf', { type: 'application/pdf' }));
    dt.items.add(new File(['x'], 'fine.jpg', { type: 'image/jpeg' }));
    document.getElementById('editor').dispatchEvent(new DragEvent('drop', { dataTransfer: dt, bubbles: true, cancelable: true }));
  });
  expect(await page.evaluate(() => requests.map(r => r.name))).toEqual(['fine.jpg']);
  expect(await page.evaluate(() => notices)).toEqual([
    'This image is larger than the upload limit.',
    'HEIC photos are not supported. Please export the photo as JPG.',
    'Please use a JPG, PNG, GIF, WebP, AVIF or SVG image.',
  ]);
});

test('media library input uploads multiple files with combined progress', async ({ page }) => {
  await setup(page);
  await page.evaluate(() => {
    document.body.insertAdjacentHTML('beforeend', '<input type="file" id="lib" multiple data-nb-upload="nb-insert-media">');
    const input = document.getElementById('lib');
    nb_upload.init_uploader(input);
    window.ready = [];
    window.progress_log = [];
    document.addEventListener('nb_upload_ready', e => e.detail.success && ready.push(e.detail.files.uuid));
    document.addEventListener('nb_upload_progress', e => progress_log.push(e.detail));
    const dt = new DataTransfer();
    ['a.png', 'b.pdf', 'c.png', 'd.png'].forEach(name => dt.items.add(new File(['x'], name)));
    input.files = dt.files;
    input.dispatchEvent(new Event('change'));
  });
  expect(await page.evaluate(() => requests.map(r => r.name))).toEqual(['a.png', 'b.pdf', 'c.png']);
  await page.evaluate(() => requests[0].progress(50, 100));
  expect(await page.evaluate(() => progress_log.at(-1))).toMatchObject({ total: 4, done: 0, progress: 0.125 });
  await complete(page, 0, 'a');
  await complete(page, 1, 'b', false);
  await expect.poll(() => page.evaluate(() => requests.length)).toBe(4);
  await complete(page, 2, 'c');
  await complete(page, 3, 'd');
  await expect.poll(() => page.evaluate(() => progress_log.at(-1).total)).toBe(0);
  expect(await page.evaluate(() => ready)).toEqual(['a', 'c', 'd']);
  expect(await page.evaluate(() => notices)).toEqual(['b.pdf: Upload failed']);
  expect(await page.evaluate(() => document.getElementById('lib').value)).toBe('');
});
