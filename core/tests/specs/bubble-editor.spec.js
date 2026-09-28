import { test, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';

const root = new URL('../../', import.meta.url);

// the real toolbar template, with shortcodes resolved the way the server would
async function toolbar_template(declared = {}) {
  const tpl = await readFile(new URL('tpl/bubble-editor/index.tpl', root), 'utf8');
  return tpl
    .replace('[#bubble-editor-buttons#]', JSON.stringify(declared))
    .replace(/\[#text ([^#]+)#\]/g, '$1');
}

async function editor_page(page, options = {}, html = '<p>Hello world</p>', tag = 'div', declared = {}) {
  page.on('pageerror', (error) => { throw error; });
  await page.setContent(`<form><${tag} data-nb-edit="body" data-nb-edit-options='${JSON.stringify(options)}'>${html}</${tag}></form>`
    + await toolbar_template(declared));
  for (const file of ['nb_bubble_editor.jsx', 'nb_edit.jsx']) {
    const script = await readFile(new URL('../js/' + file, root), 'utf8');
    await page.addScriptTag({ content: script.replace(/export default \w+;/, '') });
  }
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
  expect(await ed.innerHTML()).toBe('<p>Hello world</p>');
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
