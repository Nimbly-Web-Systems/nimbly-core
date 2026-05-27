import { readdirSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';

const output_path = 'ext/data/.i18n/text.base.po';
const entries = [];
const messages = new Map();

function collect_text_po(dir) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === 'node_modules' || entry.name === '.git') {
        continue;
      }
      collect_text_po(path);
    } else if (entry.isFile() && entry.name === 'text.po') {
      entries.push(path);
    }
  }
}

collect_text_po('.');
entries.sort();

function po_unquote(line) {
  const start = line.indexOf('"');
  const stop = line.lastIndexOf('"');
  if (start === -1 || stop <= start) {
    return '';
  }
  return line.slice(start + 1, stop).replace(/\\"/g, '"').replace(/\\n/g, '\n');
}

function parse_po(content) {
  let msgid = null;
  let msgstr = '';
  let active = null;

  function commit() {
    if (msgid !== null && msgid !== '') {
      messages.set(msgid, msgstr);
    }
  }

  for (const raw_line of content.split(/\r?\n/)) {
    const line = raw_line.trim();
    if (line.startsWith('msgid ')) {
      commit();
      msgid = po_unquote(line);
      msgstr = '';
      active = 'msgid';
    } else if (line.startsWith('msgstr ')) {
      msgstr = po_unquote(line);
      active = 'msgstr';
    } else if (line.startsWith('"') && active === 'msgid') {
      msgid += po_unquote(line);
    } else if (line.startsWith('"') && active === 'msgstr') {
      msgstr += po_unquote(line);
    }
  }
  commit();
}

function po_quote(value) {
  return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n');
}

for (const path of entries) {
  parse_po(readFileSync(path, 'utf8'));
}

const content = [...messages.entries()]
  .map(([msgid, msgstr]) => `msgid "${po_quote(msgid)}"\nmsgstr "${po_quote(msgstr)}"`)
  .join('\n\n');

mkdirSync(dirname(output_path), { recursive: true });
writeFileSync(output_path, content ? `${content}\n` : '');
console.log(`Merged text: ${entries.length} source file(s), ${messages.size} string(s) -> ${output_path}`);
