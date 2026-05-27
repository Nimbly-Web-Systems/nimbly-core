import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname } from 'node:path';

const [src, dst] = process.argv.slice(2);

if (!src || !dst) {
  console.error('Usage: node core/cli/copy-file.mjs <src> <dst>');
  process.exit(1);
}

mkdirSync(dirname(dst), { recursive: true });
copyFileSync(src, dst);
