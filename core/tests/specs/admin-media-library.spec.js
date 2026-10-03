import { test, expect } from '@playwright/test';

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', 'test@nimbly.dev');
  await page.fill('[name=password]', 'testpass123');
  await page.click('[type=submit]');
  await page.waitForURL(url => !url.toString().includes('/login'));
}

const TYPES = ['image/jpeg', 'image/jpeg', 'image/svg+xml', 'video/mp4', 'application/pdf'];
const EXTENSIONS = ['jpg', 'jpg', 'svg', 'mp4', 'pdf'];
const UNUSED = ['file007', 'file012'];

// 100 files, file000 newest; every fifth one is jpg, jpg, svg, mp4, pdf
function library() {
  const files = {};
  for (let i = 0; i < 100; i++) {
    const uuid = 'file' + String(i).padStart(3, '0');
    files[uuid] = {
      uuid,
      name: `item-${String(99 - i).padStart(3, '0')}.${EXTENSIONS[i % 5]}`,
      type: TYPES[i % 5],
      size: 1000,
      width: 300,
      height: 300,
      _created: 1700000000 - i,
      _modified: 1700000000 - i,
      title: [],
      description: [],
    };
  }
  files.file003.title = { nl: 'Zonsondergang', en: 'Sunset' };
  files.file004.title = 'Legacy handbook';
  files.file010.description = { en: 'Harbour at dawn' };
  return files;
}

async function mock_library(page) {
  const deleted = [];
  await page.route('**/api/v1/.files_meta', route =>
    route.fulfill({ json: { success: true, '.files_meta': library() } }));
  await page.route('**/api/v1/.files-unused**', route => {
    const ids = new URL(route.request().url()).searchParams.get('_ids');
    const unused = ids ? UNUSED.filter(id => ids.split(',').includes(id)) : UNUSED;
    return route.fulfill({ json: { success: true, '.files_unused': unused, count: unused.length } });
  });
  await page.route('**/api/v1/.files-usage', route => {
    const used = {};
    Object.keys(library()).filter(id => !UNUSED.includes(id)).forEach((id, i) => {
      used[id] = i < 3 ? ['(content)'] : ['inventory_items'];
    });
    used.file020.push('(content)');
    return route.fulfill({ json: { success: true, '.files_usage': { used, groups: [
      { key: 'inventory_items', name: 'Inventory items', count: 95 },
      { key: '(content)', name: 'Site content', count: 4 },
    ] } } });
  });
  await page.route('**/api/v1/.files/*', route => {
    deleted.push(route.request().url().split('/').pop());
    return route.fulfill({ json: { success: true } });
  });
  await page.route(/\/(img|video)\/file\d+/, route => route.fulfill({ status: 404, body: '' }));
  return deleted;
}

const tiles = page => page.locator('#nb-media-grid > div');
const toolbar = page => page.locator('#nb-media-toolbar');
const search = page => toolbar(page).getByLabel('Search', { exact: true });

test.describe('admin media library', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
    await mock_library(page);
  });

  test('searches name, titles in any language and descriptions', async ({ page }) => {
    await page.goto('/nb-admin/media');
    await expect(tiles(page)).toHaveCount(40);
    await expect(toolbar(page)).toContainText('100');

    await search(page).fill('item-099');
    await expect(tiles(page)).toHaveCount(1);

    await search(page).fill('zonsonder');
    await expect(tiles(page)).toHaveCount(1);
    await expect(tiles(page).first()).toContainText(/Sunset|Zonsondergang/);

    await search(page).fill('legacy hand');
    await expect(tiles(page)).toHaveCount(1);

    await search(page).fill('harbour');
    await expect(tiles(page)).toHaveCount(1);

    await search(page).fill('nothing matches this');
    await expect(tiles(page)).toHaveCount(0);
    await expect(page.getByText('No files found')).toBeVisible();

    await toolbar(page).getByLabel('Clear search').click();
    await expect(tiles(page)).toHaveCount(40);
  });

  test('filters by type and sorts', async ({ page }) => {
    await page.goto('/nb-admin/media');
    await toolbar(page).getByLabel('Type').selectOption('vid');
    await expect(toolbar(page)).toContainText('20');
    await expect(tiles(page)).toHaveCount(20);

    // svg counts as an image
    await toolbar(page).getByLabel('Type').selectOption('img');
    await expect(toolbar(page)).toContainText('60');

    await toolbar(page).getByLabel('Type').selectOption('doc');
    const names = () => page.locator('[x-data="media_library"]').evaluate(el => el._x_dataStack[0].files.map(f => f.name));
    expect((await names())[0]).toBe('item-095.pdf');
    await toolbar(page).getByLabel('Sort').selectOption('oldest');
    expect((await names())[0]).toBe('item-000.pdf');
    await toolbar(page).getByLabel('Sort').selectOption('name');
    expect((await names()).slice(0, 2)).toEqual(['item-000.pdf', 'item-005.pdf']);
  });

  test('filters by where files are used', async ({ page }) => {
    await page.goto('/nb-admin/media');
    const used_in = toolbar(page).getByLabel('Location');
    // picked before the usage scan has answered
    await used_in.selectOption('(unused)');
    await expect(tiles(page)).toHaveCount(2);

    await expect(used_in.locator('option')).toHaveText(
      ['All locations', 'Not in use', 'Inventory items (95)', 'Site content (4)']);
    await used_in.selectOption('(content)');
    await expect(tiles(page)).toHaveCount(4);
    await used_in.selectOption('inventory_items');
    await expect(toolbar(page)).toContainText('95');
    await used_in.selectOption('');
    await expect(tiles(page)).toHaveCount(40);
  });

  test('renders more files while scrolling', async ({ page }) => {
    await page.goto('/nb-admin/media');
    await expect(tiles(page)).toHaveCount(40);
    await tiles(page).last().scrollIntoViewIfNeeded();
    await expect(tiles(page)).toHaveCount(80);
    await tiles(page).last().scrollIntoViewIfNeeded();
    await expect(tiles(page)).toHaveCount(100);
  });

  test('keeps deletes and uploads consistent while filtered', async ({ page }) => {
    await page.goto('/nb-admin/media');
    await search(page).fill('.mp4');
    await expect(tiles(page)).toHaveCount(20);

    page.on('dialog', dialog => dialog.accept());
    await tiles(page).first().click();
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(tiles(page)).toHaveCount(19);

    await page.evaluate(() => document.dispatchEvent(new CustomEvent('nb_upload_ready', {
      detail: { success: true, files: { uuid: 'fresh001', name: 'fresh.jpg', type: 'image/jpeg', size: 1, width: 10, height: 10, _created: Math.floor(Date.now() / 1000), _modified: 0 } },
    })));
    await expect(tiles(page)).toHaveCount(19);

    await toolbar(page).getByLabel('Clear search').click();
    await expect(toolbar(page)).toContainText('100');
    const uuids = await page.locator('[x-data="media_library"]').evaluate(el => el._x_dataStack[0].files.map(f => f.uuid));
    expect(uuids[0]).toBe('fresh001');
    expect(uuids).not.toContain('file003');
  });

  test('picker keeps its type restriction and opens clean', async ({ page }) => {
    await page.goto('/nb-admin/test-records');
    const modal = page.locator('#nb-modal-insert-media');
    await page.evaluate(() => {
      nb.media_alpine.mode = 'select';
      nb.media_alpine.filter(['img', 'svg']);
      nb.modal.open('nb-modal-insert-media');
    });
    await expect(modal.locator('#nb-media-toolbar')).toContainText('60');
    await expect(modal.getByLabel('Type')).toBeHidden();

    await modal.getByLabel('Search', { exact: true }).fill('item-09');
    // item-090..099: six images, two videos, two documents
    await expect(modal.locator('#nb-media-grid > div')).toHaveCount(6);

    await page.evaluate(() => nb.media_alpine.filter());
    const modal_tiles = modal.locator('#nb-media-grid > div');
    await expect(modal_tiles).toHaveCount(40);
    await modal_tiles.last().scrollIntoViewIfNeeded();
    await expect(modal_tiles).toHaveCount(80);

    await modal.getByLabel('Search', { exact: true }).fill('item-09');
    await page.evaluate(() => nb.media_alpine.filter(['doc']));
    await expect(modal.getByLabel('Search', { exact: true })).toHaveValue('');
    await expect(modal.locator('#nb-media-toolbar')).toContainText('20');
  });
});
