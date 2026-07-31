import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';

const version_path = 'ext/static/app.version';
const static_roots = ['core/static', 'ext/static'];

function static_files(dir, files = []) {
  if (!existsSync(dir)) return files;
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) static_files(path, files);
    else if (entry.isFile() && path !== version_path) files.push(path);
  }
  return files;
}

export function write_app_version() {
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
