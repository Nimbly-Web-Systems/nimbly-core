import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const root = new URL('../../..', import.meta.url).pathname;
const check = (...args) => execFileSync('php', ['core/cli/architecture_check.php', ...args], {
  cwd: root,
  encoding: 'utf8',
});

function runCheck(...args) {
  try {
    return { status: 0, output: check(...args) };
  } catch (error) {
    return {
      status: error.status ?? 1,
      output: `${error.stdout ?? ''}${error.stderr ?? ''}${error.message ?? ''}`,
    };
  }
}

function makeExt() {
  return mkdtempSync(join(tmpdir(), 'nimbly-architecture-ext-'));
}

function write(path, contents) {
  mkdirSync(path.substring(0, path.lastIndexOf('/')), { recursive: true });
  writeFileSync(path, contents);
}

test('architecture check passes a clean fixture', () => {
  const ext = makeExt();
  write(`${ext}/lib/prepare-items.php`, `<?php
function prepare_items_sc($_params)
{
    set_variable('items.ready', true);
}
`);
  write(`${ext}/uri/blog/(slug)/index.tpl`, `<h1>[#get title#]</h1>`);
  write(`${ext}/uri/blog/(slug)/route.inc`, `<?php router_accept();`);
  write(`${ext}/data/.routes/blog-route`, JSON.stringify({ route: 'blog/(slug)', order: 200 }));

  expect(check('--strict', `--path=${ext}`)).toContain('Architecture check passed');
});

test('architecture check warns about HTML in PHP libraries', () => {
  const ext = makeExt();
  write(`${ext}/lib/render-card.php`, `<?php
function render_card_sc($_params)
{
    return '<div class="card">Wrong layer</div>';
}
`);

  const result = runCheck('--strict', `--path=${ext}`);

  expect(result.status).toBe(1);
  expect(result.output).toContain('contains HTML markup in a PHP library string');
});

test('architecture check warns about missing dynamic route records', () => {
  const ext = makeExt();
  write(`${ext}/uri/news/(slug)/index.tpl`, `<h1>News</h1>`);
  write(`${ext}/uri/news/(slug)/route.inc`, `<?php router_accept();`);
  write(`${ext}/data/.routes/.meta`, JSON.stringify({ fields: false }));

  const result = runCheck('--strict', `--path=${ext}`);

  expect(result.status).toBe(1);
  expect(result.output).toContain('has no matching record');
});

test('architecture check warns about accepted dynamic routes without a template', () => {
  const ext = makeExt();
  write(`${ext}/uri/news/(slug)/route.inc`, `<?php router_accept();`);
  write(`${ext}/data/.routes/news-route`, JSON.stringify({ route: 'news/(slug)', order: 200 }));

  const result = runCheck('--strict', `--path=${ext}`);

  expect(result.status).toBe(1);
  expect(result.output).toContain('calls router_accept() but has no sibling index.tpl');
});

test('architecture check allows registered dynamic redirect routes without a template', () => {
  const ext = makeExt();
  write(`${ext}/uri/old/(slug)/route.inc`, `<?php
$parts = router_match(__FILE__);
if ($parts === false) return;
load_library('redirect');
redirect('new/' . $parts[0], 301);
`);
  write(`${ext}/data/.routes/old-route`, JSON.stringify({ route: 'old/(slug)', order: 200 }));

  expect(check('--strict', `--path=${ext}`)).toContain('Architecture check passed');
});

test('architecture check warns about block-style if templates', () => {
  const ext = makeExt();
  write(`${ext}/tpl/example/index.tpl`, `[#if foo=bar#]Wrong[#/if#]`);

  const result = runCheck('--strict', `--path=${ext}`);

  expect(result.status).toBe(1);
  expect(result.output).toContain('block-style [#if#]');
});

test('architecture check warns about malformed block-style if closing tags', () => {
  const ext = makeExt();
  write(`${ext}/tpl/example/index.tpl`, `[#if foo=bar#]Wrong[#/#if]`);

  const result = runCheck('--strict', `--path=${ext}`);

  expect(result.status).toBe(1);
  expect(result.output).toContain('block-style [#if#]');
});
