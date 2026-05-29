# Contributing to Nimbly

Nimbly uses a two-repository project model:

- `core/` is the framework.
- `ext/` is the application, content, templates, and project-specific code.

Framework changes belong in the core repository. Project customizations belong
in an `ext/` repository. See `NIMBLY.md` for a full description of the stack.

## Reporting Bugs

Before opening a bug report, please check the current documentation in
`NIMBLY.md` and search existing issues.

Useful bug reports include:

- Nimbly version or git commit
- PHP version
- Node.js version
- Deployment style: Docker, manual VPS, or local dev
- Clear steps to reproduce
- Expected behavior
- Actual behavior, including logs or error output

Do not open public issues for vulnerabilities. See `SECURITY.md`.

## Suggesting Changes

Feature requests are welcome when they describe a concrete project need. Nimbly
prefers small, composable building blocks over broad abstractions.

Good proposals explain:

- What problem you are trying to solve
- Why existing templates, resources, shortcodes, or modules are not enough
- What a minimal solution would look like

## Development

Prepare a checkout:

```bash
./nimbly init
```

Run the usual checks before opening a pull request:

```bash
./nimbly build
find core -name '*.php' -print0 | xargs -0 -n1 php -l
```

Use Docker for local development when host PHP is unavailable:

```bash
npm run up
```

## Code Style

- PHP uses snake_case for functions, variables, parameters, and file names.
- Keep shortcode functions short; put logic in libraries and rendering in
  templates.
- Prefer existing Nimbly building blocks before adding custom code.
- Do not add block-style `[#if#]... [#/if#]`; `[#if#]` is self-closing.
- Do not modify `core/` for project-specific behavior.

## Commits

Use Conventional Commits:

```text
fix: prevent scheduler token from leaking into git remotes
docs: clarify Docker deployment setup
feat: add environment-specific schedule publishing
```

Keep commit messages short, specific, and professional.
