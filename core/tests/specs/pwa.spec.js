import { test, expect } from '@playwright/test';
import { createServer } from 'node:http';
import { readFileSync } from 'node:fs';
import { extname, join } from 'node:path';

const root = new URL('../../..', import.meta.url).pathname;
const static_dir = join(root, 'core/static');
let server;
let origin;

const content_types = {
  '.css': 'text/css',
  '.ico': 'image/x-icon',
  '.js': 'application/javascript',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
  '.webmanifest': 'application/manifest+json',
};

test.beforeAll(async () => {
  server = createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname === '/Demo/') {
      response.setHeader('Content-Type', 'text/html');
      response.end(`<!doctype html><html><head>
        <link rel="manifest" href="/Demo/manifest.webmanifest?v=test-1">
        <link rel="icon" href="/Demo/favicon.svg?v=test-1" type="image/svg+xml">
      </head><body><script>
        navigator.serviceWorker.register('/Demo/service-worker.js?v=test-1', { scope: '/Demo/' });
      </script></body></html>`);
      return;
    }

    if (url.pathname === '/Demo/app.css') {
      response.setHeader('Content-Type', 'text/css');
      response.end('body { color: black; }');
      return;
    }
    if (url.pathname === '/Demo/app.js') {
      response.setHeader('Content-Type', 'application/javascript');
      response.end('window.app_loaded = true;');
      return;
    }
    if (url.pathname === '/Demo/api/v1/records') {
      response.setHeader('Content-Type', 'application/json');
      response.end('{"success":true}');
      return;
    }

    const name = url.pathname.replace('/Demo/', '');
    const allowed = new Set([
      'manifest.webmanifest',
      'service-worker.js',
      'favicon.ico',
      'favicon.svg',
      'favicon-32x32.png',
      'apple-touch-icon.png',
      'pwa-icon-192.png',
      'pwa-icon-512.png',
      'pwa-icon-maskable-512.png',
    ]);
    if (allowed.has(name)) {
      response.setHeader('Content-Type', content_types[extname(name)] || 'application/octet-stream');
      if (name === 'manifest.webmanifest' || name === 'service-worker.js') {
        response.setHeader('Cache-Control', 'no-cache, must-revalidate');
      }
      response.end(readFileSync(join(static_dir, name)));
      return;
    }

    response.statusCode = 404;
    response.end('Not found');
  });

  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  origin = `http://127.0.0.1:${server.address().port}`;
});

test.afterAll(async () => {
  await new Promise((resolve) => server.close(resolve));
});

test('core exposes the modern favicon and opt-in PWA templates', () => {
  const favicon = readFileSync(join(root, 'core/tpl/html/favicon.tpl'), 'utf8');
  const scripts = readFileSync(join(root, 'core/tpl/html/scripts.tpl'), 'utf8');

  expect(favicon).toContain('favicon.ico?v=[#app-modified#]');
  expect(favicon).toContain('favicon.svg?v=[#app-modified#]');
  expect(favicon).toContain('apple-touch-icon.png?v=[#app-modified#]');
  expect(favicon).toContain('data.config.site.pwa.enabled=true tpl=pwa-head');
  expect(scripts).toContain('data.config.site.pwa.enabled=true tpl=pwa-register tpl_else=pwa-unregister');
});

test('service worker caches only the versioned static allowlist under an alias', async ({ page, context }) => {
  await page.goto(`${origin}/Demo/`);
  await page.evaluate(() => navigator.serviceWorker.ready);
  await page.waitForFunction(() => navigator.serviceWorker.controller !== null);

  const state = await page.evaluate(async () => {
    await fetch('/Demo/api/v1/records');
    const cache = await caches.open('nimbly-static-Demo-test-1');
    const requests = await cache.keys();
    return {
      scope: (await navigator.serviceWorker.ready).scope,
      cached: requests.map((request) => new URL(request.url).pathname),
    };
  });

  expect(state.scope).toBe(`${origin}/Demo/`);
  expect(state.cached).toContain('/Demo/app.css');
  expect(state.cached).toContain('/Demo/app.js');
  expect(state.cached).toContain('/Demo/pwa-icon-maskable-512.png');
  expect(state.cached).not.toContain('/Demo/');
  expect(state.cached).not.toContain('/Demo/api/v1/records');

  await context.setOffline(true);
  await expect(page.evaluate(() => fetch('/Demo/app.css?v=test-1').then((response) => response.ok))).resolves.toBe(true);
  await expect(page.evaluate(() => fetch(`/Demo/?offline=${Date.now()}`).then(() => true).catch(() => false))).resolves.toBe(false);
});
