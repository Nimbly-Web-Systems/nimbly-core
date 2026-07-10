import { spawnSync, spawn } from 'node:child_process';
import { readdirSync, statSync, mkdirSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import esbuild from 'esbuild';

const use_color = process.stdout.isTTY && process.env.NO_COLOR === undefined;
const dim   = (s) => use_color ? `\x1b[2m${s}\x1b[0m`  : s;
const green = (s) => use_color ? `\x1b[32m${s}\x1b[0m` : s;
const cyan  = (s) => use_color ? `\x1b[36m${s}\x1b[0m` : s;
const red   = (s) => use_color ? `\x1b[31m${s}\x1b[0m` : s;

function ts() {
  return dim(new Date().toLocaleTimeString('en-US', { hour12: false }));
}
function log(msg) { console.log(`${ts()}  ${msg}`); }
function ok(msg)  { console.log(`${ts()}  ${green('ok')} ${msg}`); }
function fail(msg) { console.error(`${ts()}  ${red('error')} ${msg}`); }

function rule() {
  return '─'.repeat(Math.min(process.stdout.columns || 80, 96));
}
function section(title) {
  console.log(cyan(rule()));
  console.log(title);
  console.log(cyan(rule()));
}

function write_version() {
  const path = 'ext/static/app.version';
  mkdirSync(dirname(path), { recursive: true });
  writeFileSync(path, `${Math.floor(Date.now() / 1000)}\n`);
}

// ─── initial build ────────────────────────────────────────────────────────────

section('Initial build');
const init_build = spawnSync('npm', ['run', '--silent', 'build'], { stdio: 'inherit' });
if (init_build.status !== 0) process.exit(init_build.status ?? 1);
ok('build complete');
console.log('');

// ─── tailwind watch ───────────────────────────────────────────────────────────

const tw_proc = spawn(
  'npx',
  ['tailwindcss', '-i', './css/tw/in.css', '-o', './css/tw/out.css', '--watch=always'],
  { stdio: ['ignore', 'pipe', 'pipe'] }
);

function print_tw(data) {
  const line = data.toString().trim();
  if (line) log(`tw  ${line}`);
}
tw_proc.stdout.on('data', print_tw);
tw_proc.stderr.on('data', print_tw);

// ─── esbuild CSS watch ────────────────────────────────────────────────────────

function version_plugin(label) {
  return {
    name: 'write-version',
    setup(build) {
      build.onEnd((result) => {
        if (result.errors.length === 0) {
          write_version();
          ok(`${label} rebuilt`);
        } else {
          fail(`${label} build failed`);
        }
      });
    },
  };
}

const css_ctx = await esbuild.context({
  entryPoints: ['css/index.css'],
  bundle: true,
  minify: true,
  external: ['img/*', 'font/*'],
  outfile: 'ext/static/app.css',
  logLevel: 'silent',
  plugins: [version_plugin('css')],
});
await css_ctx.watch();

// ─── esbuild JS watch ─────────────────────────────────────────────────────────

const js_ctx = await esbuild.context({
  entryPoints: ['js/index.jsx'],
  bundle: true,
  minify: true,
  outfile: 'ext/static/app.js',
  logLevel: 'silent',
  plugins: [version_plugin('js')],
});
await js_ctx.watch();

// ─── text.po watch (polling) ──────────────────────────────────────────────────

function find_text_po(dir, out = []) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === 'node_modules' || entry.name === '.git' || entry.name === '.index') continue;
    const path = join(dir, entry.name);
    if (entry.isDirectory()) find_text_po(path, out);
    else if (entry.isFile() && entry.name === 'text.po') out.push(path);
  }
  return out;
}

const po_mtimes = new Map();
for (const f of find_text_po('.')) {
  try { po_mtimes.set(f, statSync(f).mtimeMs); } catch { /* skip */ }
}

let po_timer = null;
setInterval(() => {
  let changed = false;
  for (const f of find_text_po('.')) {
    try {
      const mtime = statSync(f).mtimeMs;
      if (po_mtimes.get(f) !== mtime) {
        po_mtimes.set(f, mtime);
        changed = true;
      }
    } catch { /* skip */ }
  }
  if (!changed) return;
  clearTimeout(po_timer);
  po_timer = setTimeout(() => {
    const result = spawnSync(process.execPath, ['core/cli/merge-text-po.mjs'], { stdio: 'inherit' });
    if (result.status === 0) {
      write_version();
      ok('text rebuilt');
    }
  }, 300);
}, 2000);

// ─── shutdown ─────────────────────────────────────────────────────────────────

function shutdown() {
  tw_proc.kill();
  css_ctx.dispose();
  js_ctx.dispose();
  process.exit(0);
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);

section('Watching assets  (Ctrl+C to stop)');
