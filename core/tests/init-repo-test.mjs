import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { chmodSync, copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

// Test the host CLI without installing dependencies or starting PHP or Docker.
const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const temp = mkdtempSync(join(tmpdir(), 'nimbly-init-repo-'));
const bin = join(temp, 'bin');
const env = { PATH: `${bin}:${process.env.PATH}`, NO_COLOR: '1', GIT_CONFIG_NOSYSTEM: '1', GIT_CONFIG_GLOBAL: '/dev/null' };

function run(command, args, cwd, run_env = env) {
  const result = spawnSync(command, args, { cwd, env: run_env, encoding: 'utf8', timeout: 10000 });
  assert.ifError(result.error);
  return result;
}

function init_run(args, env_repo, existing) {
  const site = mkdtempSync(join(temp, 'site-'));
  mkdirSync(join(site, 'core/cli'), { recursive: true });
  copyFileSync(join(root, 'nimbly'), join(site, 'nimbly'));
  chmodSync(join(site, 'nimbly'), 0o755);
  copyFileSync(join(root, 'core/cli/nimbly-runner.mjs'), join(site, 'core/cli/nimbly-runner.mjs'));
  if (existing) {
    mkdirSync(join(site, 'ext'));
    writeFileSync(join(site, 'ext/keep.txt'), 'Keep this file\n');
    if (existing === 'repository') {
      assert.equal(run('git', ['init', '-q', 'ext'], site).status, 0);
    }
  }
  const log = join(site, 'calls.jsonl');
  const run_env = { ...env, INIT_TEST_LOG: log };
  if (env_repo !== undefined) run_env.EXT_REPO = env_repo;
  const result = run('./nimbly', args, site, run_env);
  const calls = existsSync(log) ? readFileSync(log, 'utf8').trim().split('\n').map((line) => JSON.parse(line)) : [];
  return { ...result, site, calls };
}

function assert_setup(result, repo, command = 'php') {
  assert.equal(result.status, 0, result.stderr);
  assert.deepEqual(result.calls.map((call) => call.command), ['npm', command, 'npm']);
  assert.deepEqual(result.calls.map((call) => call.repo), [repo, repo, repo], 'the selected repository reaches every setup step');
  assert.equal(result.calls[0].args[0], 'ci');
  assert.deepEqual(result.calls[2].args, ['run', '--silent', 'build']);
}

try {
  mkdirSync(bin);
  const stub = `#!${process.execPath}
const { appendFileSync } = require('node:fs');
const { basename } = require('node:path');
if (process.argv.slice(2).includes('--version')) process.exit(0);
appendFileSync(process.env.INIT_TEST_LOG, JSON.stringify({
  command: basename(process.argv[1]), args: process.argv.slice(2), repo: process.env.EXT_REPO || ''
}) + '\\n');
`;
  for (const command of ['php', 'npm', 'docker']) {
    writeFileSync(join(bin, command), stub, { mode: 0o755 });
  }
  const repo = join(temp, 'application source');
  mkdirSync(repo);
  writeFileSync(join(repo, 'readme.md'), 'Application fixture\n');
  for (const args of [
    ['init', '-q'], ['add', 'readme.md'],
    ['-c', 'user.name=Test', '-c', 'user.email=test@example.test', '-c', 'commit.gpgsign=false', 'commit', '-qm', 'fixture'],
  ]) {
    const result = run('git', args, repo);
    assert.equal(result.status, 0, result.stderr);
  }

  let result = init_run(['init', repo], join(temp, 'unused-repository'));
  assert_setup(result, repo);
  assert.ok(existsSync(join(result.site, 'ext/.git')), 'the positional repository is cloned');
  assert.equal(readFileSync(join(result.site, 'ext/readme.md'), 'utf8'), 'Application fixture\n');
  assert.deepEqual(result.calls[1].args, ['core/cli/nimbly.php', 'system:setup']);

  result = init_run(['init'], repo);
  assert_setup(result, repo);
  assert.ok(existsSync(join(result.site, 'ext/.git')), 'EXT_REPO still works without an argument');

  result = init_run(['--docker', 'init', repo]);
  assert_setup(result, repo, 'docker');
  assert.ok(result.calls[1].args.includes(`EXT_REPO=${repo}`), 'Docker receives the selected repository explicitly');
  assert.deepEqual(result.calls[1].args.slice(-4), ['nimbly', 'php', 'core/cli/nimbly.php', 'system:setup']);

  for (const existing of ['files', 'repository']) {
    const unused = join(temp, 'must-not-be-cloned');
    result = init_run(['init', unused], undefined, existing);
    assert_setup(result, unused);
    assert.equal(readFileSync(join(result.site, 'ext/keep.txt'), 'utf8'), 'Keep this file\n');
    assert.ok(!existsSync(join(result.site, 'ext/readme.md')), 'existing ext is not replaced');
  }
  assert_setup(init_run(['init'], undefined, 'repository'), '');

  result = init_run(['init', join(temp, 'missing-repository')]);
  assert.notEqual(result.status, 0);
  assert.deepEqual(result.calls, [], 'clone failure stops before dependencies or setup');

  for (const args of [['init', repo, 'extra'], ['init', '--unknown'], ['init', '   ']]) {
    result = init_run(args);
    assert.equal(result.status, 1);
    assert.match(result.stderr, /Usage:/);
    assert.deepEqual(result.calls, []);
    assert.ok(!existsSync(join(result.site, 'ext')), 'invalid arguments have no setup side effects');
  }
  for (const args of [['init', '--help'], ['init', repo, '--help'], ['--docker', 'init', '--help']]) {
    result = init_run(args);
    assert.equal(result.status, 0);
    assert.ok(result.stdout.includes('./nimbly init [repository]'));
    assert.deepEqual(result.calls, []);
    assert.ok(!existsSync(join(result.site, 'ext')), 'help does not clone or run setup');
  }
  console.log(`${basename(fileURLToPath(import.meta.url))}: passed`);
} finally {
  rmSync(temp, { recursive: true, force: true });
}
