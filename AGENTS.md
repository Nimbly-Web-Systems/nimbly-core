See [NIMBLY.md](NIMBLY.md) for the complete Nimbly implementation reference.

## Workflow

For every task, follow this workflow unless explicitly instructed otherwise.

1. Start from `main` or `master` with the latest live changes merged in.
   - Development must happen on the main development branch, usually `main` or `master`.
   - Check the current branch and working tree status.
   - Fetch the latest remote changes.
   - Update the local `main` or `master` branch.
   - Check whether the live/production branch has changes that are not yet in `main` or `master`.
   - If live has newer changes, merge those live changes into `main` or `master` before editing.
   - Do not develop directly on the live/production branch.
   - Remember that `ext/` is a separate git repository. Application changes use git inside `ext/`.

2. Plan the work.
   - Inspect the relevant code first.
   - Break the task into small, logical steps.
   - Do not start with broad rewrites.

3. Implement one logical step.
   - Keep the change focused.
   - Prefer existing Nimbly building blocks.
   - Follow the conventions in `NIMBLY.md`.

4. Sanity test the step.
   - Run relevant local checks.
   - Use Playwright for UI/browser sanity testing when frontend behavior is affected.
   - Compare the result against the requested outcome, not only against whether the code compiles.

5. Adjust until correct.
   - If the sanity test or visual result is not correct, fix it before moving on.
   - Re-run the relevant check after adjustments.

6. Commit the completed step.
   - Commit only after the step is implemented and sanity-tested.
   - Use Conventional Commits.
   - Keep commit messages short, specific, professional, and usually one line.
   - Do not add commercial noise like `Co-Authored-By`.

7. Repeat.
   - Continue step by step until the task is complete.

8. Final handoff.
   - Summarize what changed.
   - List commits created.
   - List tests/checks performed.
   - Mention anything not completed or not verified.

## Restrictions

- Never push to a remote branch unless explicitly instructed.
- Never create, merge, or close pull requests unless explicitly instructed.
- Never run destructive git commands such as `reset --hard`, `clean`, forced push, rebase, or branch deletion unless explicitly instructed.
- Do not discard, overwrite, or remove existing local changes unless explicitly instructed.
- Do not modify `core/` unless the task is explicitly framework work.

## At a glance

- **`ext/` is a separate git repository.** Always run git commands inside
  `ext/` for application changes, for example `git -C ext status`. The project
  root is the `core` repo and knows nothing about `ext/` changes.
- Work in `ext/` for project customizations. Do not modify `core/` unless the
  task is explicitly framework work.
- Follow the PHP naming convention documented in `NIMBLY.md`: snake_case
  everywhere for functions, variables, parameters, and file names. Do not use
  camelCase or PascalCase in PHP.
- Prefer proper fixes over hacks. If a layout, field type, or data flow is
  wrong, fix the underlying issue rather than hiding the symptom.
- Use existing Nimbly building blocks first: core libraries, templates, and
  established shortcodes before adding custom code.
- Build self-contained UI features as reusable components in `ext/tpl/<name>/`
  or focused custom shortcodes in `ext/lib/<name>/`, not as inline page code.
- Keep shortcode functions short. They should coordinate data and rendering,
  with logic and layout separated into libraries/templates where possible.
- Never add `route.inc` to a static route, one with no `(param)` URL segments.
  `route.inc` exists only for dynamic routes that need to call `router_accept()`
  or `router_deny()`. Adding it to a static route causes a 404.
- **`[#if#]` has no block form — ever.** There is no `[#if#]…[#/if#]` syntax.
  `[#if#]` is always a single self-closing tag; conditional content lives in a
  separate template via `tpl=`. This is by design: templates contain no business
  logic. Never write block-style conditionals in templates.
- **`[#set#]` does not overwrite by default.** Without the `overwrite` param a
  `[#set#]` on an already-set variable is a no-op. Route templates can therefore
  set page variables early and core/shared templates act as fallbacks. Use
  `overwrite` only when you explicitly need to replace an existing value, for
  example passing data into a reusable template component.

Commit messages must follow the Conventional Commits style documented in
`NIMBLY.md`: short, specific, professional, and usually one line.