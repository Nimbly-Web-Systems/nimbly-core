import { execSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';

const version_path = 'ext/static/app.version';
// Core commit the assets were built from; the admin dashboard warns when core moved on.
const core_path = 'ext/static/app.core';
const static_roots = ['core/static', 'ext/static'];

function static_files(dir, files = []) {
  if (!existsSync(dir)) return files;
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) static_files(path, files);
    else if (entry.isFile() && path !== version_path && path !== core_path) files.push(path);
  }
  return files;
}

function write_app_core() {
  let commit = '';
  try {
    commit = execSync('git rev-parse HEAD', { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
  } catch {
    return;
  }
  if (!/^[0-9a-f]{40}$/.test(commit)) return;
  const current = existsSync(core_path) ? readFileSync(core_path, 'utf8') : '';
  if (current !== `${commit}\n`) writeFileSync(core_path, `${commit}\n`);
}

export function write_app_version() {
  write_app_core();
  const hash = createHash('sha256');
  const files = static_roots.flatMap((root) => static_files(root)).sort();
  for (const file of files) {
    hash.update(relative('.', file));
    hash.update('\0');
    hash.update(readFileSync(file));
    hash.update('\0');
  }
  const version = `${hash.digest('hex').slice(0, 12)}\n`;
  const current = existsSync(version_path) ? readFileSync(version_path, 'utf8') : '';
  if (current === version) return false;
  mkdirSync(dirname(version_path), { recursive: true });
  writeFileSync(version_path, version);
  return true;
}
