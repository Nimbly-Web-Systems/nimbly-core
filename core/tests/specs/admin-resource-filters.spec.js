import { test, expect } from '@playwright/test';

async function login(page) {
  await page.goto('/login');
  await page.fill('[name=email]', 'test@nimbly.dev');
  await page.fill('[name=password]', 'testpass123');
  await page.click('[type=submit]');
  await page.waitForURL(url => !url.toString().includes('/login'));
}

function filter(page, field_id) {
  return page.locator(`select[name="filter[${field_id}]"]`);
}

function desktop_record(page, title) {
  return page.getByRole('cell', { name: title, exact: true });
}

test.describe('admin resource filters', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('applies defaults with AND logic and allows explicit All', async ({ page }) => {
    await page.goto('/nb-admin/test-records');
    await expect(filter(page, 'status')).toHaveValue('active');
    await expect(filter(page, 'delivery_status')).toHaveValue('sendable');
    await expect(desktop_record(page, 'Alpha record')).toBeVisible();
    await expect(desktop_record(page, 'Beta record')).toBeHidden();

    await filter(page, 'delivery_status').selectOption('');
    await expect(page).toHaveURL(/filter%5Bdelivery_status%5D=/);
    await expect(desktop_record(page, 'Beta record')).toBeVisible();
    await page.reload();
    await expect(filter(page, 'delivery_status')).toHaveValue('');
  });

  test('combines boolean, select, multi-value, and search filters', async ({ page }) => {
    await page.goto('/nb-admin/test-records?filter%5Bdelivery_status%5D=');
    const total_records = await page.locator('section[x-data]').evaluate(el => el._x_dataStack[0].record_count());
    await filter(page, 'is_paid').selectOption('1');
    await expect(desktop_record(page, 'Beta record')).toBeVisible();
    await expect(desktop_record(page, 'Alpha record')).toBeHidden();

    await filter(page, 'is_paid').selectOption('');
    await filter(page, 'tags').selectOption('featured');
    await expect(desktop_record(page, 'Alpha record')).toBeVisible();
    await expect(desktop_record(page, 'Beta record')).toBeHidden();

    await page.getByRole('searchbox').fill('Beta');
    await expect(page.locator('table').getByText('No matching records', { exact: true })).toBeVisible();
    await expect(page.locator('section[x-data] h3')).toContainText(new RegExp(`0\\s+of\\s+${total_records}\\s+records`));
  });

  test('normalizes invalid URL values to All instead of the default', async ({ page }) => {
    await page.goto('/nb-admin/test-records?filter%5Bstatus%5D=invalid&filter%5Bdelivery_status%5D=');
    await expect(filter(page, 'status')).toHaveValue('');
    await expect(page).toHaveURL(/filter%5Bstatus%5D=/);
    await expect(desktop_record(page, 'Alpha record')).toBeVisible();
    await expect(desktop_record(page, 'Beta record')).toBeVisible();
  });

  test('resets pagination after a filter change', async ({ page }) => {
    await page.goto('/nb-admin/test-records?filter%5Bdelivery_status%5D=');
    const created_ids = await page.evaluate(async () => {
      const ids = [];
      for (let ix = 0; ix < 11; ix += 1) {
        const uuid = `filter-page-${ix}`;
        await nb.api.post(`${nb.base_url}/api/v1/test-records`, {
          uuid,
          title: `Filter page ${ix}`,
          score: String(ix),
          status: 'active',
          delivery_status: 'sendable',
          is_paid: false,
          category: 'news',
          tags: ['seasonal'],
        });
        ids.push(uuid);
      }
      return ids;
    });

    try {
      await page.reload();
      await page.locator('select[x-model="page_size"]').selectOption('10');
      await page.locator('button[x-ref="btn_next_page"]').click();
      await expect(page.locator('section[x-data]')).toHaveAttribute('x-data', 'data_table()');
      expect(await page.locator('section[x-data]').evaluate(el => el._x_dataStack[0].page)).toBe(2);

      await filter(page, 'is_paid').selectOption('1');
      expect(await page.locator('section[x-data]').evaluate(el => el._x_dataStack[0].page)).toBe(1);
      await expect(desktop_record(page, 'Beta record')).toBeVisible();
    } finally {
      await page.evaluate(async ids => {
        for (const uuid of ids) {
          await nb.api.delete(`${nb.base_url}/api/v1/test-records/${uuid}`);
        }
      }, created_ids);
    }
  });

  test('renders responsive full-width controls on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/nb-admin/test-records');
    const status_box = await filter(page, 'status').boundingBox();
    const delivery_box = await filter(page, 'delivery_status').boundingBox();
    expect(status_box.width).toBeGreaterThan(300);
    expect(delivery_box.y).toBeGreaterThan(status_box.y);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
  });
});
