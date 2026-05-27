import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';

const path = 'ext/static/app.version';

mkdirSync(dirname(path), { recursive: true });
writeFileSync(path, `${Math.floor(Date.now() / 1000)}\n`);
