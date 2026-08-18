import { test, expect } from '@playwright/test';

// js/nb_edit.jsx's MediumEditor init defaulted to forcePlainText, so pasting
// Word/Google Docs content into an html field dropped all inline formatting
// (bold/italic/links) and wrapped every line of a poem's soft line breaks in
// its own <p>, inflating line spacing via .prose's paragraph margins. Fixed
// by an opt-in per-field `paste_html` flag (default false, so every other
// html field keeps today's plain-text-paste behavior) enabled only on
// articles.body, which turns on MediumEditor's cleanPastedHTML.
//
// The corresponding attribute-stripping write-side hardening
// (sanitize_html_fields()/sanitize_html_attrs() in api.php) is covered
// separately against the sandboxed test-i18n-records resource, not a real
// article, since it applies to every `type: html` field project-wide.

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', 'test@nimbly.dev');
  await page.fill('[name=password]', 'testpass123');
  await page.click('[type=submit]');
  await page.waitForURL((url) => !url.toString().includes('/login'));
}

function paste_event_script(html) {
  return `(() => {
    const el = document.querySelector('[data-nb-edit="body"]');
    if (!el) return 'NO_EL';
    el.focus();
    el.innerHTML = '';
    const dt = new DataTransfer();
    dt.setData('text/html', ${JSON.stringify(html)});
    dt.setData('text/plain', el.textContent);
    const evt = new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true });
    el.dispatchEvent(evt);
    return el.innerHTML;
  })()`;
}

test.describe('article body paste formatting', () => {
  test('bold, italic, and a link survive paste', async ({ page }) => {
    await login(page);
    await page.goto('/nb-admin/test-i18n-records/test-i18n-001');
    await expect(page.locator('[data-nb-edit="body"]')).toBeVisible();

    const result = await page.evaluate(paste_event_script(
      '<p>Some <b>bold</b> and <i>italic</i> and <a href="https://example.com">a link</a></p>'
    ));

    expect(result).not.toBe('NO_EL');
    expect(result).toMatch(/<b[ >][^<]*bold<\/b>/);
    expect(result).toMatch(/<i[ >][^<]*italic<\/i>/);
    expect(result).toContain('href="https://example.com"');
  });

  test('a poem with soft line breaks pastes as one paragraph with <br>, not one <p> per line', async ({ page }) => {
    await login(page);
    await page.goto('/nb-admin/test-i18n-records/test-i18n-001');
    await expect(page.locator('[data-nb-edit="body"]')).toBeVisible();

    const result = await page.evaluate(paste_event_script(
      '<p>Line one<br>Line two<br>Line three</p>'
    ));

    expect(result).not.toBe('NO_EL');
    const p_count = (result.match(/<p[ >]/g) || []).length;
    expect(p_count).toBeLessThanOrEqual(1);
    expect(result).toContain('<br>');
  });
});

test.describe('write-side html attribute sanitization', () => {
  test('an onerror attribute is stripped from a saved html field, other language untouched', async ({ page }) => {
    await login(page);
    const original = await page.evaluate(async () => {
      const r = await fetch('/api/v1/test-i18n-records/test-i18n-001');
      return (await r.json())['test-i18n-records']['test-i18n-001'];
    });

    try {
      await page.evaluate(async () => {
        await window.nb.api.put(`${window.nb.base_url}/api/v1/test-i18n-records/test-i18n-001`, {
          body: { en: '<img src="x" onerror="alert(1)"><p>clean</p>' },
        });
      });
      const after = await page.evaluate(async () => {
        const r = await fetch('/api/v1/test-i18n-records/test-i18n-001');
        return (await r.json())['test-i18n-records']['test-i18n-001'];
      });
      expect(after.body.en).not.toContain('onerror');
      expect(after.body.en).toContain('clean');
      expect(after.body.nl).toBe(original.body.nl); // untouched language/field left alone
    } finally {
      await page.evaluate(async (body) => {
        await window.nb.api.put(`${window.nb.base_url}/api/v1/test-i18n-records/test-i18n-001`, { body });
      }, original.body);
    }
  });
});
