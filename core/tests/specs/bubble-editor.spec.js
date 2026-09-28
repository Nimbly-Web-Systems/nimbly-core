import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';

const root = new URL('../../', import.meta.url);

// the real toolbar template, with shortcodes resolved the way the server would
async function toolbar_template(declared = {}) {
  const tpl = await readFile(new URL('tpl/bubble-editor/index.tpl', root), 'utf8');
  // the built app.css, so visibility and positioning are the real thing
  const css = '<style>' + await readFile(new URL('../ext/static/app.css', root), 'utf8') + '</style>';
  return css + tpl
    .replace('[#bubble-editor-buttons#]', JSON.stringify(declared))
    .replace(/\[#text ([^#]+)#\]/g, '$1');
}

async function load_scripts(page) {
  for (const file of ['nb_bubble_editor.jsx', 'nb_field_bar.jsx', 'nb_edit.jsx']) {
    const script = await readFile(new URL('../js/' + file, root), 'utf8');
    await page.addScriptTag({ content: script.replace(/export default \w+;/, '') });
  }
}

async function editor_page(page, options = {}, html = '<p>Hello world</p>', tag = 'div', declared = {}) {
  page.on('pageerror', (error) => { throw error; });
  await page.setContent(`<form><${tag} data-nb-edit="body" data-nb-edit-options='${JSON.stringify(options)}'>${html}</${tag}></form>`
    + await toolbar_template(declared));
  await load_scripts(page);
  await page.evaluate(() => {
    window.nb = { text: { medium_editor_placeholder: 'Type here' }, bubble_editor: window.nb_bubble_editor };
    nb_bubble_editor.enabled = () => true;
    const ed = document.querySelector('[data-nb-edit]');
    window.changes = [];
    ed.addEventListener('nb:editor-change', (e) => { window.changes.push(e.detail.value); });
    nb_edit.init_editor(ed, true);
  });
  return page.locator('[data-nb-edit]');
}

async function select_text(page, text) {
  await page.evaluate((text) => {
    const ed = document.querySelector('[data-nb-edit]');
    const walker = document.createTreeWalker(ed, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
      const i = walker.currentNode.textContent.indexOf(text);
      if (i >= 0) {
        ed.focus();
        const range = document.createRange();
        range.setStart(walker.currentNode, i);
        range.setEnd(walker.currentNode, i + text.length);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        return;
      }
    }
    throw new Error('text not found: ' + text);
  }, text);
}

const toolbar = (page) => page.locator('.nb-bubble-toolbar');
const button = (page, label) => toolbar(page).getByRole('button', { name: label, exact: true });

test('selection shows only configured, known buttons', async ({ page }) => {
  await editor_page(page, { buttons: 'format,bold,italic,link,ul,ol' });
  await expect(toolbar(page)).toBeHidden();
  await select_text(page, 'world');
  await expect(toolbar(page)).toBeVisible();
  await expect(toolbar(page).locator('[data-nb-bubble-name]')).toHaveCount(2);
  await expect(button(page, 'Bold')).toBeVisible();
  await expect(button(page, 'Italic')).toBeVisible();
});

test('bold toggles, reports active state and emits a form change', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'bold,italic' });
  await select_text(page, 'world');
  await button(page, 'Bold').click();
  await expect(ed).toContainText('world');
  expect(await ed.innerHTML()).toBe('<p>Hello <b>world</b></p>');
  await expect(button(page, 'Bold')).toHaveAttribute('aria-pressed', 'true');
  expect(await page.evaluate(() => window.changes.at(-1))).toBe('<p>Hello <b>world</b></p>');
  await button(page, 'Bold').click();
  expect(await ed.innerHTML()).toBe('<p>Hello world</p>');
});

test('headings and quote toggle back to a paragraph', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'h2,h3,quote' });
  await select_text(page, 'world');
  await button(page, 'Heading 2').click();
  expect(await ed.innerHTML()).toBe('<h2>Hello world</h2>');
  await expect(button(page, 'Heading 2')).toHaveAttribute('aria-pressed', 'true');
  await button(page, 'Heading 2').click();
  expect(await ed.innerHTML()).toBe('<p>Hello world</p>');
  await button(page, 'Quote').click();
  expect(await ed.innerHTML()).toBe('<blockquote>Hello world</blockquote>');
});

test('lists are not nested inside a paragraph', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'orderedlist,unorderedlist' });
  await select_text(page, 'world');
  await button(page, 'Bulleted list').click();
  expect(await ed.innerHTML()).toBe('<ul><li>Hello world</li></ul>');
  await expect(button(page, 'Bulleted list')).toHaveAttribute('aria-pressed', 'true');
});

test('link can be added via the toolbar form and removed again', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'bold,anchor' });
  await select_text(page, 'world');
  await button(page, 'Link').click();
  const input = toolbar(page).locator('input');
  await expect(input).toBeFocused();
  await input.fill('example.com/page');
  await input.press('Enter');
  expect(await ed.innerHTML()).toBe('<p>Hello <a href="https://example.com/page">world</a></p>');
  await expect(ed).toBeFocused();

  await select_text(page, 'world');
  await expect(button(page, 'Link')).toHaveAttribute('aria-pressed', 'true');
  await button(page, 'Link').click();
  await toolbar(page).getByRole('button', { name: 'Remove link' }).click();
  expect(await ed.innerHTML()).toBe('<p>Hello world</p>');
});

test('the link button edits an existing link, prefilled, for the whole link', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'anchor' }, '<p>Hello <a href="https://a.com">big world</a></p>');
  await select_text(page, 'world'); // only part of the link
  await button(page, 'Link').click();
  const input = toolbar(page).locator('input');
  await expect(input).toHaveValue('https://a.com');
  await expect(toolbar(page).getByRole('button', { name: 'Remove link' })).toBeVisible();
  await input.fill('b.com');
  await input.press('Enter');
  expect(await ed.innerHTML()).toBe('<p>Hello <a href="https://b.com">big world</a></p>');
});

test('ctrl+k with the caret in a link edits it; an emptied URL removes it', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'anchor' }, '<p>Hello <a href="https://a.com">world</a></p>');
  await select_text(page, 'or');
  await page.evaluate(() => getSelection().collapseToStart());
  await page.keyboard.press('ControlOrMeta+k');
  const input = toolbar(page).locator('input');
  await expect(input).toHaveValue('https://a.com');
  await input.fill('');
  await input.press('Enter');
  expect(await ed.innerHTML()).toBe('<p>Hello world</p>');
});

test('a new link has no remove button', async ({ page }) => {
  await editor_page(page, { buttons: 'anchor' });
  await select_text(page, 'world');
  await button(page, 'Link').click();
  await expect(toolbar(page).locator('input')).toHaveValue('');
  await expect(toolbar(page).getByRole('button', { name: 'Remove link' })).toBeHidden();
});

const preview = (page) => page.locator('.nb-link-preview');

test('hovering a link shows its URL with edit and remove', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'anchor' }, '<p>Hello <a href="https://a.com/page">world</a> and more</p>');
  await expect(preview(page)).toHaveCount(0);
  await ed.locator('a').hover();
  await expect(preview(page)).toBeVisible();
  await expect(preview(page).locator('[data-nb-link-url]')).toHaveText('https://a.com/page');
  await preview(page).getByRole('button', { name: 'Edit' }).click();
  const input = toolbar(page).locator('input');
  await expect(input).toHaveValue('https://a.com/page');
  await input.fill('https://c.com');
  await input.press('Enter');
  expect(await ed.innerHTML()).toBe('<p>Hello <a href="https://c.com">world</a> and more</p>');
  await ed.locator('a').hover();
  await preview(page).getByRole('button', { name: 'Remove link' }).click();
  expect(await ed.innerHTML()).toBe('<p>Hello world and more</p>');
});

test('the caret inside a link shows its URL; leaving the link hides it', async ({ page }) => {
  await editor_page(page, { buttons: 'bold' }, '<p>Hello <a href="https://a.com">world</a> and more</p>');
  await select_text(page, 'or');
  await page.evaluate(() => getSelection().collapseToStart());
  await expect(preview(page)).toBeVisible();
  await expect(preview(page).getByRole('button', { name: 'Edit' })).toBeHidden(); // field has no link button
  await select_text(page, 'more');
  await page.evaluate(() => getSelection().collapseToStart());
  await expect(preview(page)).toBeHidden();
});


test('escape closes the link form without linking', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'anchor' });
  await select_text(page, 'world');
  await button(page, 'Link').click();
  await toolbar(page).locator('input').press('Escape');
  expect(await ed.innerHTML()).toBe('<p>Hello world</p>');
  await expect(ed).toBeFocused();
});

test('shortcuts only work for configured buttons', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'italic' });
  await select_text(page, 'world');
  await page.keyboard.press('ControlOrMeta+b');
  expect(await ed.innerHTML()).toBe('<p>Hello world</p>');
  await page.keyboard.press('ControlOrMeta+i');
  expect(await ed.innerHTML()).toBe('<p>Hello <i>world</i></p>');
});

function paste(page, data) {
  return page.evaluate((data) => {
    const ed = document.querySelector('[data-nb-edit]');
    ed.focus();
    ed.innerHTML = '';
    const dt = new DataTransfer();
    Object.entries(data).forEach(([type, value]) => dt.setData(type, value));
    ed.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
    return ed.innerHTML;
  }, data);
}

test('default paste is plain text, one paragraph per line', async ({ page }) => {
  await editor_page(page, {});
  const result = await paste(page, { 'text/html': '<p><b>Line</b> one</p>', 'text/plain': 'Line one\nLine <two>' });
  expect(result).toBe('<p>Line one</p><p>Line &lt;two&gt;</p>');
});

test('paste_html keeps formatting and strips cruft', async ({ page }) => {
  await editor_page(page, { paste_html: true });
  const result = await paste(page, {
    'text/html': '<meta charset="utf-8"><b id="docs-internal-guid-1" style="font-weight:normal"><p class="x" style="color:red">Some <span style="font-weight:700">bold</span> and <a href="https://example.com" style="color:blue">a link</a><br>next</p></b>',
    'text/plain': 'ignored',
  });
  expect(result).toBe('<p>Some <b>bold</b> and <a href="https://example.com">a link</a><br>next</p>');
});

test('placeholder shows while empty', async ({ page }) => {
  const ed = await editor_page(page, {}, '');
  await expect(ed).toHaveAttribute('data-nb-edit-empty', 'true');
  await expect(ed).toHaveAttribute('data-placeholder', 'Type here');
  await ed.click();
  await page.keyboard.type('Hi');
  await expect(ed).not.toHaveAttribute('data-nb-edit-empty');
  expect(await ed.innerHTML()).toBe('<p>Hi</p>');
});

test('destroy removes listeners and editability', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'bold' });
  await page.evaluate(() => nb_bubble_editor.destroy(document.querySelector('[data-nb-edit]')));
  await expect(ed).not.toHaveAttribute('contenteditable');
  await page.evaluate(() => {
    const ed = document.querySelector('[data-nb-edit]');
    ed.dispatchEvent(new Event('input'));
  });
  expect(await page.evaluate(() => window.changes.length)).toBe(0);
});

test('typing in a block host does not add a paragraph', async ({ page }) => {
  const h1 = await editor_page(page, { buttons: 'bold' }, 'Title', 'h1');
  await h1.click();
  await page.keyboard.press('End');
  await page.keyboard.type('!');
  expect(await h1.innerHTML()).toBe('Title!');
});

test('typing in an existing heading keeps it a heading', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'bold' }, 'Intro<h2>Heading</h2>');
  await ed.locator('h2').click();
  await page.keyboard.press('End');
  await page.keyboard.type('!');
  expect(await ed.innerHTML()).toBe('Intro<h2>Heading!</h2>');
});

test('application buttons declared in the template wrap, format and insert', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'bold,highlight,h5,divider' }, '<p>Hello world</p>', 'div', {
    highlight: { kind: 'wrap', tag: 'mark', class: 'bg-yellow-200', label: 'Highlight', icon: 'M' },
    h5: { kind: 'block', tag: 'h5', label: 'Heading 5', icon: 'H5' },
    divider: { kind: 'insert', html: '<hr>', label: 'Divider', icon: '—' },
  });
  await select_text(page, 'world');
  await expect(toolbar(page).locator('[data-nb-bubble-name]')).toHaveCount(4);
  await button(page, 'Highlight').click();
  expect(await ed.innerHTML()).toBe('<p>Hello <mark class="bg-yellow-200">world</mark></p>');
  await select_text(page, 'world');
  await expect(button(page, 'Highlight')).toHaveAttribute('aria-pressed', 'true');
  await button(page, 'Highlight').click();
  expect(await ed.innerHTML()).toBe('<p>Hello world</p>');
  await select_text(page, 'Hello');
  await button(page, 'Heading 5').click();
  expect(await ed.innerHTML()).toBe('<h5>Hello world</h5>');
});

test('buttons registered from script get the same toolbar treatment', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'upper' });
  await page.evaluate(() => {
    nb_bubble_editor.register('upper', {
      label: 'Uppercase', icon: 'AA',
      run: (ctx) => nb_bubble_editor.doc.insert_html(ctx.range.toString().toUpperCase()),
    });
    const ed = document.querySelector('[data-nb-edit]');
    nb_bubble_editor.destroy(ed);
    nb_bubble_editor.init(ed, { buttons: ['upper'], placeholder: '' });
  });
  await select_text(page, 'world');
  await button(page, 'Uppercase').click();
  expect(await ed.innerHTML()).toBe('<p>Hello WORLD</p>');
});

test('optional buttons: strikethrough, superscript, pre and clear formatting', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'strikethrough,superscript,pre,removeFormat' }, '<p>Hello <b>world</b></p>');
  await select_text(page, 'Hello');
  await button(page, 'Strikethrough').click();
  expect(await ed.innerHTML()).toBe('<p><s>Hello</s> <b>world</b></p>');
  await expect(button(page, 'Strikethrough')).toHaveAttribute('aria-pressed', 'true');
  await button(page, 'Strikethrough').click();
  expect(await ed.innerHTML()).toBe('<p>Hello <b>world</b></p>');
  await button(page, 'Strikethrough').click();
  await button(page, 'Clear formatting').click();
  expect(await ed.innerHTML()).toBe('<p>Hello <b>world</b></p>');
  await select_text(page, 'world');
  await button(page, 'Superscript').click();
  expect(await ed.innerHTML()).toBe('<p>Hello <b><sup>world</sup></b></p>');
  await button(page, 'Preformatted').click();
  expect(await ed.innerHTML()).toBe('<pre>Hello <b><sup>world</sup></b></pre>');
  await button(page, 'Preformatted').click();
  expect(await ed.innerHTML()).toBe('<p>Hello <b><sup>world</sup></b></p>');
});

test('underline is not active unless configured', async ({ page }) => {
  const ed = await editor_page(page, {});
  await select_text(page, 'world');
  await page.keyboard.press('ControlOrMeta+u');
  expect(await ed.innerHTML()).toBe('<p>Hello world</p>');
});

test('paste_html cleans Word markup', async ({ page }) => {
  await editor_page(page, { paste_html: true });
  const result = await paste(page, {
    'text/html': '<html xmlns:o="urn:schemas-microsoft-com:office:office"><head><style>p.MsoNormal{margin:0}</style></head><body lang="NL">'
      + '<!--StartFragment--><p class="MsoNormal" align="center" style="mso-line-height:normal"><font face="Calibri"><span lang="NL" style="font-size:11pt">Word <b style="mso-bidi-font-weight:normal">bold</b> text</span></font><o:p></o:p></p>'
      + '<p class="MsoNormal"><o:p>&nbsp;</o:p></p>'
      + '<p class="MsoNormal"><a href="https://example.com" target="_blank" style="color:blue">link</a></p><!--EndFragment--></body></html>',
    'text/plain': 'ignored',
  });
  expect(result).toBe('<p>Word <b>bold</b> text</p><p><a href="https://example.com">link</a></p>');
});

/* field bar */

// inline page editing: fields outside a form, edit mode on, saves stubbed
async function inline_page(page, fields, extra = '') {
  page.on('pageerror', (error) => { throw error; });
  await page.setContent(fields + extra + await toolbar_template());
  await load_scripts(page);
  await page.evaluate(() => {
    window.nb = {
      text: { medium_editor_placeholder: 'Type here' },
      bubble_editor: window.nb_bubble_editor, field_bar: window.nb_field_bar, edit: window.nb_edit,
    };
    nb_bubble_editor.enabled = () => true;
    window.saved = [];
    window.real_save_resource = nb_edit.save_resource;
    nb_edit.save_resource = (ed) => { window.saved.push(ed.innerHTML); };
    window.real_open_insert_media = nb_edit.open_insert_media;
    nb_edit.open_insert_media = () => { window.media_opened = nb_edit.active_editor.dataset.nbEdit; };
    nb_edit.enabled = true;
    document.querySelectorAll('[data-nb-edit]').forEach((ed) => nb_edit.init_editor(ed));
  });
}

const bar = (page) => page.locator('.nb-field-bar');

test('field bar shows configured buttons and save for inline editing', async ({ page }) => {
  await inline_page(page, `<div data-nb-edit="pages.p1.body.en" data-nb-edit-options='{"buttons":"bold,h2,anchor"}'><p>Hello world</p></div>`);
  await expect(bar(page)).toBeHidden();
  await page.locator('[data-nb-edit] p').click();
  await expect(bar(page)).toBeVisible();
  await expect(bar(page).locator('[data-nb-bubble-name]')).toHaveCount(3);
  await expect(bar(page).locator('[data-nb-bar-media]')).toBeHidden();
  const save = bar(page).getByRole('button', { name: 'Save' });
  await expect(save).toBeDisabled();
  await page.keyboard.press('End');
  await page.keyboard.type('!');
  await expect(save).toBeEnabled();
  await save.click();
  expect(await page.evaluate(() => window.saved)).toEqual(['<p>Hello world!</p>']);
  await expect(save).toBeDisabled();
});

test('bold from the bar applies to what is typed next', async ({ page }) => {
  await inline_page(page, `<div data-nb-edit="pages.p1.body.en" data-nb-edit-options='{"buttons":"bold"}'><p>Hello</p></div>`);
  await page.locator('[data-nb-edit] p').click();
  await page.keyboard.press('End');
  await bar(page).getByRole('button', { name: 'Bold' }).click();
  await expect(bar(page).getByRole('button', { name: 'Bold' })).toHaveAttribute('aria-pressed', 'true');
  await page.keyboard.type('X');
  expect(await page.locator('[data-nb-edit]').innerHTML()).toBe('<p>Hello<b>X</b></p>');
});

test('ctrl+s saves inline edits', async ({ page }) => {
  await inline_page(page, `<div data-nb-edit="pages.p1.body.en"><p>Hello</p></div>`);
  await page.locator('[data-nb-edit] p').click();
  await page.keyboard.type('X');
  await page.keyboard.press('ControlOrMeta+s');
  expect(await page.evaluate(() => window.saved.length)).toBe(1);
});

test('plain fields get a save-only bar', async ({ page }) => {
  await inline_page(page, `<h1 data-nb-edit="pages.p1.title.en" data-nb-edit-options='{"plain":true}'>Title</h1>`);
  await page.locator('h1').click();
  await expect(bar(page)).toBeVisible();
  await expect(bar(page).locator('[data-nb-bubble-name]')).toHaveCount(0);
  await expect(bar(page).getByRole('button', { name: 'Save' })).toBeVisible();
});

test('media inserts at the caret of the focused field', async ({ page }) => {
  await inline_page(page, `<div data-nb-edit="pages.p1.body.en" data-nb-edit-options='{"media":true}'><p>Hello</p></div>`,
    '<div id="nb-modal-insert-media"></div>');
  await page.locator('[data-nb-edit] p').click();
  await bar(page).getByRole('button', { name: 'Media' }).click();
  expect(await page.evaluate(() => window.media_opened)).toBe('pages.p1.body.en');
  await expect(page.locator('[data-nb-edit]')).toBeFocused();
});

test('form fields get their own docked bar without save', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'bold,italic' });
  await page.evaluate(() => {
    window.nb.field_bar = window.nb_field_bar; window.nb.edit = window.nb_edit;
    nb_field_bar.attach(document.querySelector('[data-nb-edit]'), { buttons: ['bold', 'italic'], save: true, docked: true });
  });
  const docked = page.locator('.nb-field-bar-docked');
  await expect(docked).toBeVisible(); // part of the field, also before focus
  expect(await page.evaluate(() => document.querySelector('[data-nb-edit]').previousElementSibling.classList.contains('nb-field-bar-docked'))).toBe(true);
  await expect(docked.locator('[data-nb-bubble-name]')).toHaveCount(2);
  await expect(docked.locator('[data-nb-bar-save]')).toBeHidden();
  // used before the field has focus: works at the end of the field
  await docked.getByRole('button', { name: 'Bold' }).click();
  await expect(ed).toBeFocused();
  await page.keyboard.type('X');
  expect(await ed.innerHTML()).toBe('<p>Hello world<b>X</b></p>');
  await page.evaluate(() => nb_field_bar.detach(document.querySelector('[data-nb-edit]')));
  await expect(docked).toHaveCount(0);
});

test('bar docks above the field and pins to the top while scrolling a long field', async ({ page }) => {
  await page.setViewportSize({ width: 800, height: 600 });
  await inline_page(page, `<div style="height:300px"></div><div data-nb-edit="pages.p1.body.en" data-nb-edit-options='{"buttons":"bold"}'>`
    + '<p>Start</p>' + '<p>text</p>'.repeat(80) + '</div><div style="height:1200px"></div>');
  await page.locator('[data-nb-edit] p').first().click();
  const top = async () => page.evaluate(() => {
    const b = document.querySelector('.nb-field-bar').getBoundingClientRect();
    const e = document.querySelector('[data-nb-edit]').getBoundingClientRect();
    return { bar_bottom: b.bottom, field_top: e.top, bar_top: b.top };
  });
  let r = await top();
  expect(r.bar_bottom).toBeLessThanOrEqual(r.field_top);
  await page.evaluate(() => window.scrollTo(0, 800));
  await page.waitForTimeout(100);
  r = await top();
  expect(r.bar_top).toBe(6);
  await page.evaluate(() => window.scrollTo(0, 5000));
  await page.waitForTimeout(100);
  await expect(bar(page)).toHaveCSS('visibility', 'hidden');
});

test('bar hides when focus leaves the field', async ({ page }) => {
  await inline_page(page, `<div style="height:120px"></div><div data-nb-edit="pages.p1.body.en"><p>Hello</p></div><input id="other">`);
  await page.locator('[data-nb-edit] p').click();
  await expect(bar(page)).toBeVisible();
  await page.locator('#other').click();
  await expect(bar(page)).toBeHidden();
});

test('the field stays the insert target while picking media', async ({ page }) => {
  await inline_page(page, `<div style="height:120px"></div><div data-nb-edit="pages.p1.body.en" data-nb-edit-options='{"media":true}'><p>Hello</p></div>`,
    '<div id="nb-modal-insert-media" class="hidden"><button id="pick">Pick</button></div>');
  await page.evaluate(() => {
    window.nb.modal = { open: (id) => document.getElementById(id).classList.remove('hidden') };
    nb_edit.open_insert_media = window.real_open_insert_media;
  });
  await page.locator('[data-nb-edit] p').click();
  await page.keyboard.press('End');
  await bar(page).getByRole('button', { name: 'Media' }).click();
  await page.locator('#pick').click();
  expect(await page.evaluate(() => nb_edit.active_editor && nb_edit.active_editor.dataset.nbEdit)).toBe('pages.p1.body.en');
  await page.evaluate(() => { nb_edit.restore_caret_pos(); nb_edit.insert_html('<img src="/img/x/480w" alt="X">'); });
  expect(await page.locator('[data-nb-edit]').innerHTML()).toBe('<p>Hello<img src="/img/x/480w" alt="X"></p>');
});

/* inserting block content at the caret */

async function insert_at(page, html_before, text, offset, insert) {
  const ed = await editor_page(page, {}, html_before);
  await page.evaluate(([text, offset, insert]) => {
    const ed = document.querySelector('[data-nb-edit]');
    const walker = document.createTreeWalker(ed, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
      if (walker.currentNode.textContent.includes(text)) {
        ed.focus();
        const r = document.createRange();
        r.setStart(walker.currentNode, walker.currentNode.textContent.indexOf(text) + offset);
        r.collapse(true);
        getSelection().removeAllRanges();
        getSelection().addRange(r);
        break;
      }
    }
    nb_edit.active_editor = ed;
    nb_edit.insert_html(insert);
  }, [text, offset, insert]);
  return ed;
}

const figure = '<figure><img src="/img/x/480w" alt="X"></figure>';

test('a figure inserted mid-paragraph splits the paragraph', async ({ page }) => {
  const ed = await insert_at(page, '<p>Hello world</p>', 'Hello', 5, figure);
  expect(await ed.innerHTML()).toBe('<p>Hello</p>' + figure + '<p> world</p>');
  expect(await page.evaluate(() => window.changes.at(-1))).toBe(await ed.innerHTML());
});

test('a figure inserted at the end leaves an empty paragraph to keep typing in', async ({ page }) => {
  const ed = await insert_at(page, '<p>Hello</p>', 'Hello', 5, figure);
  expect(await ed.innerHTML()).toBe('<p>Hello</p>' + figure + '<p><br></p>');
  await page.keyboard.type('Next');
  expect(await ed.innerHTML()).toBe('<p>Hello</p>' + figure + '<p>Next</p>');
});

test('a figure inserted at the start replaces nothing and keeps the text after it', async ({ page }) => {
  const ed = await insert_at(page, '<p>Hello</p>', 'Hello', 0, figure);
  expect(await ed.innerHTML()).toBe(figure + '<p>Hello</p>');
});

test('splitting inside bold keeps the formatting on both sides', async ({ page }) => {
  const ed = await insert_at(page, '<p><b>Hello world</b></p>', 'Hello', 5, figure);
  expect(await ed.innerHTML()).toBe('<p><b>Hello</b></p>' + figure + '<p><b> world</b></p>');
});

test('the bubble stays clear of a Nimbly bar docked on the left', async ({ page }) => {
  await editor_page(page, { buttons: 'h2,h3,bold,italic,orderedlist,unorderedlist,quote,anchor' }, '<p>Hi there</p>');
  await page.evaluate(() => {
    document.body.style.margin = '0';
    const bar = document.createElement('nav');
    bar.id = 'nb-bar';
    bar.style.cssText = 'position:fixed;left:0;top:0;width:240px;height:100vh;';
    document.body.prepend(bar);
  });
  await select_text(page, 'Hi');
  await expect(toolbar(page)).toBeVisible();
  const left = await toolbar(page).evaluate((el) => el.getBoundingClientRect().left);
  expect(left).toBeGreaterThanOrEqual(248);
});

/* keyboard access */

test('alt+f10 moves to the bar, enter applies a button to the selection, escape returns', async ({ page }) => {
  await inline_page(page, `<div style="height:120px"></div><div data-nb-edit="pages.p1.body.en" data-nb-edit-options='{"buttons":"bold,italic"}'><p>Hello world</p></div>`);
  const ed = page.locator('[data-nb-edit]');
  await select_text(page, 'world');
  await page.keyboard.press('Alt+F10');
  await expect(bar(page).getByRole('button', { name: 'Bold' })).toBeFocused();
  await page.keyboard.press('Enter');
  expect(await ed.innerHTML()).toBe('<p>Hello <b>world</b></p>');
  await expect(bar(page)).toBeVisible();
  await page.keyboard.press('Alt+F10');
  await page.keyboard.press('Tab');
  await expect(bar(page).getByRole('button', { name: 'Italic' })).toBeFocused();
  await page.keyboard.press('Space');
  expect(await ed.innerHTML()).toBe('<p>Hello <b><i>world</i></b></p>');
  await page.keyboard.press('Alt+F10');
  await page.keyboard.press('Escape');
  await expect(ed).toBeFocused();
});

test('keyboard focus leaving the floating bar hides it', async ({ page }) => {
  await inline_page(page, `<div style="height:120px"></div><div data-nb-edit="pages.p1.body.en" data-nb-edit-options='{"buttons":"bold"}'><p>Hello</p></div><input id="other">`);
  await page.locator('[data-nb-edit] p').click();
  await page.keyboard.press('Alt+F10');
  await page.evaluate(() => document.getElementById('other').focus());
  await expect(bar(page)).toBeHidden();
});

test('button labels come from the template, so they can be translated', async ({ page }) => {
  page.on('pageerror', (error) => { throw error; });
  const tpl = (await toolbar_template()).replace('<span data-name="bold">Bold</span>', '<span data-name="bold">Vet</span>');
  await page.setContent(`<form><div data-nb-edit="body" data-nb-edit-options='{"buttons":"bold"}'><p>Hallo wereld</p></div></form>` + tpl);
  await load_scripts(page);
  await page.evaluate(() => {
    window.nb = { text: { medium_editor_placeholder: '' }, bubble_editor: window.nb_bubble_editor };
    nb_bubble_editor.enabled = () => true;
    nb_edit.init_editor(document.querySelector('[data-nb-edit]'), true);
  });
  await select_text(page, 'wereld');
  await expect(button(page, 'Vet')).toBeVisible();
});

/* saving inline edits */

test('a failed save reports the error and only creates the record when it does not exist', async ({ page }) => {
  await inline_page(page, `<div data-nb-edit="pages.p1.body.en"><p>Hello</p></div>`);
  const run = (put_result) => page.evaluate(async (put_result) => {
    const calls = [];
    window.nb.base_url = '';
    window.nb.text.saved = 'Saved';
    window.nb.notify = (m) => calls.push('notify:' + m);
    window.nb.api = {
      put: async () => { calls.push('put'); return put_result; },
      post: async () => { calls.push('post'); return { success: true }; },
    };
    window.real_save_resource(document.querySelector('[data-nb-edit]'));
    await new Promise((r) => setTimeout(r, 50));
    return calls;
  }, put_result);
  expect(await run({ success: false, code: 422, message: 'INVALID_DATA' })).toEqual(['put', 'notify:INVALID_DATA']);
  expect(await run({ success: false, code: 404, message: 'RESOURCE_NOT FOUND' })).toEqual(['put', 'post', 'notify:Saved']);
});

test('an app insert button fires its event from the field bar and stays out of the bubble', async ({ page }) => {
  const ed = await editor_page(page, { buttons: 'bold,pin' }, '<p>Hello <a data-pin="p1">Paris</a> world</p>', 'div', {
    pin: { kind: 'event', event: 'nb:insert-pin', insert: true, active: '[data-pin]', label: 'Pin', icon: '<i>P</i>' },
  });
  await page.evaluate(() => {
    window.nb.field_bar = window.nb_field_bar; window.nb.edit = window.nb_edit;
    nb_field_bar.attach(document.querySelector('[data-nb-edit]'), { buttons: ['bold', 'pin'], docked: true });
    window.pins = [];
    document.addEventListener('nb:insert-pin', (e) => window.pins.push(e.detail.editor.dataset.nbEdit + ':' + e.detail.range.collapsed));
  });
  const docked = page.locator('.nb-field-bar-docked');
  const pin = docked.locator('[data-nb-bar-insert] [data-nb-bubble-name="pin"]');
  await expect(pin).toHaveText('PPin'); // icon + label
  await expect(docked.locator('[data-nb-bar-format] [data-nb-bubble-name]')).toHaveCount(1);
  await select_text(page, 'world');
  await expect(toolbar(page).locator('[data-nb-bubble-name]')).toHaveCount(1); // only bold in the bubble
  await page.evaluate(() => getSelection().collapseToEnd());
  await pin.click();
  expect(await page.evaluate(() => window.pins)).toEqual(['body:true']);
  await select_text(page, 'Paris');
  await page.evaluate(() => getSelection().collapseToStart());
  await expect(pin).toHaveAttribute('aria-pressed', 'true');
});
