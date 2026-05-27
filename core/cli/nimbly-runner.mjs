import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync } from 'node:fs';
import { mkdirSync } from 'node:fs';
import { createInterface } from 'node:readline/promises';
import { stdin as input, stdout as output } from 'node:process';

const args = process.argv.slice(2);
const force_docker = args[0] === '--docker';
const cli_args = force_docker ? args.slice(1) : args;
const has_command = cli_args.length > 0;
const command = cli_args[0] ?? '';

function command_exists(command, check_args = ['--version']) {
  const result = spawnSync(command, check_args, { stdio: 'ignore' });
  return result.status === 0;
}

function run(command, command_args) {
  const result = spawnSync(command, command_args, { stdio: 'inherit' });
  if (result.error) {
    console.error(result.error.message);
    process.exit(1);
  }
  process.exit(result.status ?? 1);
}

function run_step(command, command_args, options = {}) {
  const result = spawnSync(command, command_args, { stdio: 'inherit', ...options });
  if (result.error) {
    console.error(result.error.message);
    process.exit(1);
  }
  if (result.status !== 0) {
    process.exit(result.status ?? 1);
  }
}

const use_color = output.isTTY && process.env.NO_COLOR === undefined;
const color = {
  bold: (text) => use_color ? `\x1b[1m${text}\x1b[0m` : text,
  cyan: (text) => use_color ? `\x1b[36m${text}\x1b[0m` : text,
  green: (text) => use_color ? `\x1b[32m${text}\x1b[0m` : text,
  dim: (text) => use_color ? `\x1b[2m${text}\x1b[0m` : text,
};

function pretty_cwd() {
  const home = process.env.HOME;
  if (home && process.cwd().startsWith(home)) {
    return `~${process.cwd().slice(home.length)}`;
  }
  return process.cwd();
}

function banner(label) {
  if (!output.isTTY) {
    return;
  }

  console.log('');
  console.log(`${color.cyan('██▄  ██')}  ${color.bold('Nimbly 1.1')}`);
  console.log(`${color.cyan('██ ▀▄██')}  ${color.green(label)}`);
  console.log(`${color.cyan('██   ██')}  ${color.dim(pretty_cwd())}`);
}

function rule() {
  const columns = output.columns || 80;
  const width = Math.max(48, Math.min(columns, 96));
  return '─'.repeat(width);
}

function step(number, total, title) {
  console.log('');
  console.log(color.cyan(rule()));
  console.log(`${color.dim(`${number}/${total}`)}  ${title}`);
  console.log(color.cyan(rule()));
}

function ok(message) {
  console.log(`${color.green('ok')} ${message}`);
}

function note(message) {
  console.log(`${color.dim('note')} ${message}`);
}

function require_npm() {
  if (!command_exists('npm')) {
    console.error('npm is required for dependency installation and asset builds. Install Node 20+ and npm, then retry.');
    process.exit(1);
  }
}

function can_prompt() {
  return Boolean(input.isTTY && output.isTTY);
}

async function prompt(message) {
  const rl = createInterface({ input, output });
  try {
    return (await rl.question(message)).trim();
  } finally {
    rl.close();
  }
}

function ext_needs_repo() {
  return !existsSync('ext/.git');
}

function ext_has_files() {
  if (!existsSync('ext')) {
    return false;
  }

  return readdirSync('ext').some((name) => name !== '.' && name !== '..');
}

function clone_ext_repo(repo_url) {
  if (!command_exists('git')) {
    console.error('git is required to clone the ext repository.');
    process.exit(1);
  }

  if (ext_has_files()) {
    note('ext/ already exists but is not a git repository. Continuing with existing files.');
    note('Run git init inside ext/ when you are ready to version the application.');
    return;
  }

  run_step('git', ['clone', repo_url, 'ext']);
}

async function prepare_ext_repo() {
  if (!ext_needs_repo()) {
    return;
  }

  if (ext_has_files()) {
    note('ext/ is not a git repository yet. Continuing with existing files.');
    note('Run git init inside ext/ when you are ready to version the application.');
    return;
  }

  const env_repo = process.env.EXT_REPO?.trim() ?? '';
  if (env_repo) {
    clone_ext_repo(env_repo);
    return;
  }

  if (!can_prompt()) {
    return;
  }

  const repo_url = await prompt('Application repo URL for ext/ (leave empty to create ext/ without git): ');
  if (repo_url) {
    clone_ext_repo(repo_url);
    return;
  }

  mkdirSync('ext', { recursive: true });
}

function run_dependencies() {
  require_npm();
  run('npm', ['ci', '--silent', '--no-audit', '--fund=false']);
}

function run_build() {
  require_npm();
  run('npm', ['run', '--silent', 'build']);
}

function run_up() {
  require_npm();
  run('npm', ['run', 'up']);
}

async function run_init() {
  banner('Setup');
  step(1, 5, 'Prepare ext');
  await prepare_ext_repo();
  ok('ext ready.');
  require_npm();
  step(2, 5, 'Install dependencies');
  run_step('npm', ['ci', '--silent', '--no-audit', '--fund=false']);
  ok('dependencies ready.');
  step(3, 5, 'Set up site');
  run_step(
    process.execPath,
    ['core/cli/nimbly-runner.mjs', ...(force_docker ? ['--docker'] : []), 'site:setup'],
    { env: { ...process.env, NIMBLY_INIT: '1', NIMBLY_COMPACT_OUTPUT: '1' } }
  );
  step(4, 5, 'Build assets');
  run_step('npm', ['run', '--silent', 'build']);
  step(5, 5, 'Ready');
  console.log('Nimbly is ready.');
  console.log('Start the local Docker environment with:');
  console.log('  ./nimbly up');
  console.log('');
}

function show_common_help(show_all_hint = true) {
  console.log('Usage: ./nimbly <command>');
  console.log('');
  console.log('Common commands:');
  console.log('  init               Prepare a checkout for first use');
  console.log('  build              Build project assets');
  console.log('  up                 Start the local Docker environment');
  console.log('  user:create        Create an additional user account');
  if (show_all_hint) {
    console.log('');
    console.log('Run ./nimbly help to list every command.');
  }
  console.log('');
}

function run_host_php() {
  run('php', ['core/cli/nimbly.php', ...cli_args]);
}

function run_docker_php() {
  if (!command_exists('docker')) {
    console.error('PHP is not available and Docker was not found. Install PHP 8+ or Docker, then retry.');
    process.exit(1);
  }

  const env_keys = [
    'APP_ENV',
    'BASE_PATH',
    'EXT_REPO',
    'PEPPER',
    'SITE_NAME',
    'ADMIN_EMAIL',
    'ADMIN_PASSWORD',
    'SCHEDULE_FILE',
    'SCHEDULE_ENV',
    'NIMBLY_ENV',
    'NIMBLY_INIT',
    'MAIL_SERVICE',
    'MAIL_FROM',
    'MAIL_FROM_NAME',
    'RESEND_API_KEY',
    'SMTP_HOST',
    'SMTP_PORT',
    'SMTP_USER',
    'SMTP_PASSWORD',
    'SMTP_SECURE',
    'OPENAI_API_KEY',
  ];

  const env_args = env_keys
    .filter((key) => process.env[key] !== undefined)
    .flatMap((key) => ['-e', `${key}=${process.env[key]}`]);

  console.error(
    force_docker
      ? 'Running Nimbly CLI through Docker.'
      : 'PHP is not available on the host; running Nimbly CLI through Docker.'
  );
  run('docker', [
    'compose',
    '-f',
    'docker/docker-compose.yml',
    'run',
    '--rm',
    '--build',
    ...env_args,
    'nimbly',
    'php',
    'core/cli/nimbly.php',
    ...cli_args,
  ]);
}

if (command === 'deps') {
  run_dependencies();
}

if (command === 'build') {
  run_build();
}

if (command === 'up') {
  run_up();
}

if (command === 'init') {
  await run_init();
  process.exit(0);
}

if (!has_command) {
  show_common_help();
  process.exit(0);
}

if (command === 'help' && !force_docker && command_exists('php')) {
  console.log('Nimbly commands:');
  console.log('');
  show_common_help(false);
  console.log('All PHP-backed commands:');
  console.log('');
  run_step('php', ['core/cli/nimbly.php', 'help']);
  process.exit(0);
}

if (command === 'help' && !force_docker && !command_exists('php')) {
  show_common_help();
  console.log('PHP-backed commands:');
  console.log('  Run ./nimbly --docker help to list PHP-backed commands through Docker.');
  console.log('');
  process.exit(0);
}

if (!force_docker && command_exists('php')) {
  run_host_php();
}

run_docker_php();
