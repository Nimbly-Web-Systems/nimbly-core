import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';

const root = new URL('../../../', import.meta.url);
const soundcloud = '<iframe width="100%" height="166" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/123" title="SoundCloud" onload="window.hostile=true"></iframe>';
const with_scripts = soundcloud + '<script src="https://embed.example/first.js"></script><script src="https://embed.example/first.js"></script><script src="https://embed.example/last.js"></script>';

async function setup(page, permitted = false, options = { media: true }) {
  await page.route('https://**/*', route => route.fulfill({ contentType: 'text/html', body: '<p>External frame fixture</p>' }));
  const translate = source => source.replace(/\[#text ([^#]+)#\]/g, '$1');
  const generic = translate(await readFile(new URL('core/modules/admin/tpl/media-modal/embed_generic.tpl', root), 'utf8'))
    .replace('[#feature-cond manage-unsandboxed-embeds echo=true echo_else=false#]', String(permitted));
  const toolbar = translate(await readFile(new URL('core/tpl/bubble-editor/index.tpl', root), 'utf8')).replace('[#bubble-editor-buttons#]', '{}');
  const providers = await Promise.all(['vimeo', 'youtube'].map(async provider => translate(await readFile(new URL(`core/modules/admin/tpl/media-modal/embed_${provider}.tpl`, root), 'utf8'))));
  await page.setContent(`<form><div id="editor" data-nb-edit="body" data-nb-edit-options='${JSON.stringify(options)}'><p>Before after</p></div></form>${toolbar}<div id="nb-modal-insert-media" x-data="media_insert()" class="hidden"><div id="media_modal_embed_options">${generic}${providers.join('')}</div><button id="insert" @click="embed_media()" :disabled="!can_embed()">Insert</button></div>`);
  for (const file of ['nb_embed', 'nb_edit', 'nb_bubble_editor', 'nb_field_bar', 'nb_media_library']) {
    const source = await readFile(new URL(`js/${file}.jsx`, root), 'utf8');
    await page.addScriptTag({ content: source.replace(/export default \w+;/, '') });
  }
  await page.evaluate(() => {
    window.nb = { text: {}, base_url: '', embed: nb_embed, edit: nb_edit, bubble_editor: nb_bubble_editor, field_bar: nb_field_bar,
      media_library: nb_media_library, media_modal: {}, api: { get: async () => ({ success: true, '.files_meta': {} }) },
      modal: { open: id => document.getElementById(id).classList.remove('hidden'), close: id => document.getElementById(id).classList.add('hidden') } };
    nb_edit.init_editor(document.getElementById('editor'), true);
  });
  await page.addScriptTag({ content: await readFile(new URL('core/tpl/nimblybar/index.js', root), 'utf8') });
  await page.addScriptTag({ path: new URL('node_modules/alpinejs/dist/cdn.js', root).pathname });
  await page.waitForFunction(() => !!nb.media_alpine);
  await page.evaluate(() => {
    const editor = document.getElementById('editor');
    editor.focus();
    const range = document.createRange();
    range.setStart(editor.querySelector('p').firstChild, 7);
    range.collapse(true);
    getSelection().removeAllRanges(); getSelection().addRange(range);
  });
}
async function paste(page, code) {
  await page.evaluate(code => {
    const data = new DataTransfer(); data.setData('text/plain', code);
    document.getElementById('editor').dispatchEvent(new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: data }));
  }, code);
}

test('plain iframe paste keeps caret, title, pasted height, surrounding text and mobile width', async ({ page }) => {
  await setup(page);
  await paste(page, soundcloud);
  const frame = page.locator('#editor iframe');
  await expect(frame).toHaveAttribute('sandbox', /allow-scripts/);
  await expect(frame).toHaveAttribute('title', 'SoundCloud');
  await expect(frame).toHaveAttribute('height', '166');
  await page.keyboard.type('continued ');
  expect(await page.locator('#editor').textContent()).toBe('Before continued after');
  expect(await page.evaluate(() => window.hostile)).toBeUndefined();
  const stored = await page.evaluate(() => nb.edit.editor_html(document.getElementById('editor')));
  expect(stored).not.toContain('srcdoc');
  await page.evaluate(stored => { document.getElementById('editor').innerHTML = stored; }, stored);
  await page.setViewportSize({ width: 375, height: 750 });
  expect((await frame.boundingBox()).width).toBeLessThanOrEqual(375);
});

test('scripts require review, never execute in editor, and existing embed reopens', async ({ page }) => {
  await setup(page);
  await paste(page, with_scripts);
  await expect(page.locator('#nb-generic-controls')).toBeVisible();
  await expect(page.locator('#nb-generic-preview iframe')).toHaveCount(1);
  const checkbox = page.getByLabel('Sandbox enabled');
  await expect(checkbox).toBeChecked(); await expect(checkbox).toBeDisabled();
  await page.locator('#insert').click();
  await expect(page.locator('#editor iframe')).toHaveCount(1);
  expect(await page.evaluate(() => nb.embed.info(document.querySelector('#editor iframe')).scripts.length)).toBe(2);
  await page.evaluate(() => {
    const frame = document.querySelector('#editor iframe');
    nb.edit.active_editor = document.getElementById('editor');
    nb.embed.open(nb.embed.code(nb.embed.info(frame)), frame);
  });
  await page.getByLabel('Accessible title').fill('Reopened player');
  await page.locator('#insert').click();
  await expect(page.locator('#editor iframe')).toHaveCount(1);
  await expect(page.locator('#editor iframe')).toHaveAttribute('title', 'Reopened player');
});

test('admin can disable sandbox, sees warning, preview remains sandboxed', async ({ page }) => {
  await setup(page, true);
  await paste(page, with_scripts);
  await page.getByLabel('Sandbox enabled').uncheck();
  await expect(page.getByText('External scripts can access this page and affect its behaviour.')).toBeVisible();
  await page.locator('#insert').click();
  await expect(page.locator('#editor iframe')).toHaveAttribute('sandbox', /allow-scripts/);
  const stored = await page.evaluate(() => nb.edit.editor_html(document.getElementById('editor')));
  expect(stored).not.toContain(' sandbox=');
  expect(stored).not.toContain('<script');
});

test('reject inline scripts and srcdoc, preserve YouTube and Vimeo controls', async ({ page }) => {
  await setup(page);
  await paste(page, soundcloud + '<script>window.hostile=true</script>');
  await expect(page.locator('[role=alert]')).toContainText('Only external HTTPS scripts');
  await expect(page.locator('#insert')).toBeDisabled();
  await page.evaluate(() => { nb.media_alpine.embed_info.active = 'youtube'; nb.media_alpine.embed_info.youtube.id = 'abcdefghijk'; });
  await expect(page.locator('#media_modal_embed_options [data-nb-embed=youtube] iframe')).toHaveAttribute('src', 'https://www.youtube.com/embed/abcdefghijk');
  await page.evaluate(() => { nb.media_alpine.embed_info.active = 'vimeo'; nb.media_alpine.embed_info.vimeo.id = '123456789'; });
  await expect(page.locator('#media_modal_embed_options [data-nb-embed=vimeo] iframe')).toHaveAttribute('src', /player.vimeo.com\/video\/123456789/);
  await page.evaluate(() => {
    nb.edit.active_editor = document.getElementById('editor');
    nb.media_alpine.embed_media();
  });
  await expect(page.locator('#editor iframe')).toHaveAttribute('sandbox', /allow-scripts/);
  expect(await page.evaluate(() => nb.embed.info(document.querySelector('#editor iframe')).proportional)).toBe(true);
});

test('sandboxed companion script cannot access parent or navigate top', async ({ page }) => {
  const markup = execFileSync('php', ['-r', `require 'core/lib/embed.php'; echo embed_markup(['src'=>'https://frame.example/player','title'=>'Isolated','width'=>640,'height'=>166,'sandbox'=>true,'proportional'=>false,'scripts'=>['https://script.example/test.js']], true);`], { cwd: root }).toString();
  await page.route('https://frame.example/**', route => route.fulfill({ body: 'Player' }));
  await page.route('https://script.example/test.js', route => route.fulfill({ contentType: 'text/javascript', body: `let denied=false;try{parent.document.body.dataset.escape='yes'}catch{denied=true}let navigation=false;try{top.location.href='https://escape.example'}catch{navigation=true}parent.postMessage({denied,navigation},'*');` }));
  await page.setContent('<script>window.messages=[];addEventListener("message",e=>messages.push(e.data))</script>' + markup);
  expect(await page.evaluate(() => window.messages)).toEqual([]);
  await page.addScriptTag({ content: (await readFile(new URL('js/nb_embed.jsx', root), 'utf8')).replace(/export default \w+;/, '') });
  await page.evaluate(() => { nb_embed.init(); document.dispatchEvent(new CustomEvent('nb:ready')); });
  await expect.poll(() => page.evaluate(() => window.messages)).toContainEqual({ denied: true, navigation: true });
  expect(await page.locator('body').getAttribute('data-escape')).toBeNull();
});

test('external collector waits for startup, deduplicates and continues after failed or throwing scripts', async ({ page }) => {
  const loader = execFileSync('php', ['-r', `function load_library($n){require_once 'core/lib/'.$n.'.php';} require 'core/lib/collect-script.php'; foreach(['failed','throwing','last','last'] as $name) collect_external_script('https://script.example/'.$name.'.js'); render_collected_scripts();`], { cwd: root }).toString();
  const requested = [];
  await page.route('https://script.example/**', async route => {
    const name = route.request().url().split('/').pop(); requested.push(name);
    if (name === 'failed.js') return route.abort();
    await route.fulfill({ contentType: 'text/javascript', body: name === 'throwing.js' ? 'throw new Error("fixture")' : 'window.last_loaded=true' });
  });
  await page.setContent(loader);
  expect(requested).toEqual([]);
  await page.evaluate(() => { window.started = true; document.dispatchEvent(new CustomEvent('nb:ready')); });
  await page.waitForFunction(() => window.last_loaded);
  expect(requested).toEqual(['failed.js', 'throwing.js', 'last.js']);
});

test('configured blog template accepts embeds and surrounding content without publishing', async ({ page }) => {
  test.skip(!process.env.EMBED_BLOG_EXT, 'Set EMBED_BLOG_EXT to a local site ext checkout for a read-only template preview.');
  const site = new URL('file://' + process.env.EMBED_BLOG_EXT.replace(/\/$/, '') + '/');
  const meta = JSON.parse(await readFile(new URL('data/blog/.meta', site), 'utf8'));
  await setup(page, true, meta.fields.main_text);
  await paste(page, soundcloud);
  await page.keyboard.type('After the player');
  const stored = await page.evaluate(() => nb.edit.editor_html(document.getElementById('editor')));
  const rendered = execFileSync('php', ['-r', `require 'core/lib/embed.php'; echo embed_normalize_html(stream_get_contents(STDIN), true);`], { cwd: root, input: stored }).toString();
  const template = (await readFile(new URL('uri/blog/data.blog.tpl', site), 'utf8'))
    .replace('[#get-html item.main_text#]', rendered)
    .replace(/x-show="[^"]*"/g, '').replace(/\[#(?:get|if)\s[^\n]*?#\]/g, '');
  const css = await readFile(new URL('static/app.css', site), 'utf8');
  await page.setContent('<style>' + css + '</style>' + template);
  await expect(page.locator('iframe')).toHaveAttribute('height', '166');
  await expect(page.locator('body')).toContainText('Before After the playerafter');
  await page.setViewportSize({ width: 375, height: 750 });
  expect((await page.locator('iframe').boundingBox()).width).toBeLessThanOrEqual(375);
});
