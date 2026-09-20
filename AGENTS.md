# Nimbly Agent Guide

Nimbly is a full-stack atomic design system and digital product platform covering structure, behavior, implementation, and reusable application building blocks.

This file is the default starting point for development work.

**Do not read `NIMBLY.md` in full by default.** It is the complete implementation reference and is intentionally large.

Inspect the relevant implementation first. When additional framework documentation is needed, retrieve only the necessary material using:

```bash
./nimbly docs:list
./nimbly docs:search "<term>"
./nimbly docs:section "<section>"
```

Use `docs:list` only when you do not know which capability or documentation section is relevant. When the target is known, go directly to `docs:search` or `docs:section`.

Read the complete `NIMBLY.md` only when targeted lookup has proved insufficient for a genuinely framework-wide task.

For project-specific workflow rules, also read .context/AGENTS.md and, when present, ext/.context/AGENTS.md once per session. Treat .context/AGENTS.md as applying to the project as a whole and ext/.context/AGENTS.md as applying specifically to work in the separate ext/ repository.

## 1. Start from current application state

Development must happen on the main development branch, normally `main` or `master`.

Before diagnosing or modifying application code:

* Check the current branch and working tree.
* Fetch the latest remote state.
* Update the local development branch.
* Check whether the live or production branch contains changes not yet present in the development branch.
* Merge newer live changes into `main` or `master` before editing.
* Do not develop directly on the live or production branch.
* Preserve unrelated local changes.

Production may contain live-edited resource data that has been published through the normal synchronization workflow. Do not diagnose or implement against a stale checkout.

Remember that `ext/` is a separate Git repository. Application Git operations normally use:

```bash
git -C ext status
```

For existing Nimbly synchronization workflows, reuse the established commands and implementation. Do not create replacement synchronization mechanisms merely to obtain current state.

## 2. Choose the correct repository

Nimbly combines two independent repositories:

* `core/`: reusable framework functionality and building blocks.
* `ext/`: application-specific routes, data, templates, libraries, modules, agents, configuration, and theme.

Before editing, ask:

> Would every Nimbly application need this?

If yes, it is probably framework work and belongs in `core/`.

If it belongs specifically to this application, it belongs in `ext/`.

If the distinction is unclear, inspect the existing implementation first and, if needed, retrieve:

```bash
./nimbly docs:section "Project Structure > Deciding core vs. ext"
```

Do not default to `ext/` merely because it appears safer.

Do not modify `core/` unless the task genuinely requires framework work.

## 3. Inspect before designing

For an existing feature, bug, or integration:

1. Inspect the current implementation.
2. Identify the specific behavior, data flow, integration point, or missing capability.
3. Search `core/` and `ext/` for an existing Nimbly building block or established project pattern.
4. Retrieve targeted documentation only if necessary.
5. Make the smallest change that addresses the root cause.

For bug fixes, prefer correcting the existing mechanism over designing a replacement system.

Do not expand into:

* adjacent cleanup,
* speculative edge cases,
* generalized abstractions,
* replacement persistence,
* new APIs,
* new schedulers,
* new permission systems,
* new synchronization layers,
* or architectural redesign

unless they are actually required to solve the requested problem.

If the task unexpectedly requires substantial new architecture or becomes materially larger than expected, stop and report the discrepancy before implementing the broader design.

## 4. Reuse Nimbly building blocks

Prefer existing Nimbly functionality before adding custom infrastructure.

Before creating a new library, subsystem, persistence mechanism, API, or abstraction:

1. Search the relevant `core/` and `ext/` code.
2. Check whether an established Nimbly capability already exists.
3. Use `docs:search` or `docs:section` if its behavior is unclear.
4. Extend the existing pattern where appropriate.
5. Introduce something new only when the framework genuinely lacks the required capability.

Do not introduce architecture merely because a local solution could theoretically be generalized.

## 5. Keep responsibilities separated

Use the established application structure.

### Templates

Use:

```text
ext/tpl/
ext/uri/
```

for markup and presentation.

Do not generate template-owned HTML inside PHP libraries.

### Libraries

Use:

```text
ext/lib/
```

for PHP business logic, data preparation, backend behavior, and integration code.

### Modules

Use:

```text
ext/modules/
```

for reusable application functionality where appropriate.

### Resources

Application resources live under:

```text
ext/data/<resource>/
```

with `.meta` defining fields, validation, indexes, permissions, and lifecycle behavior.

Before inventing custom storage, check whether the existing Nimbly resource/data system already solves the problem.

## 6. Routes and templates

Folders under `ext/uri/` map to application routes.

Static routes generally use an `index.tpl`.

Dynamic routes containing segments such as:

```text
(slug)
```

may require matching route definitions and `route.inc`.

Never add `route.inc` to a static route. `route.inc` exists for dynamic routing that needs `router_accept()` or `router_deny()`. Adding it to a static route causes a 404.

When route definitions change, use the established route synchronization tooling where required:

```bash
./nimbly routes:sync
```

Reusable presentation belongs in `ext/tpl/`.

## 7. Template syntax rules

### `[#if#]`

`[#if#]` has no block form.

Never write:

```text
[#if#]
...
[/#if#]
```

It is always a self-closing tag.

Use `tpl=` to render conditional template content or `echo=` to output a conditional value.

Conditional markup belongs in a separate template.

### `[#set#]`

`[#set#]` does not overwrite an existing value by default.

This allows route templates to set page variables early while shared or core templates act as fallbacks.

Use `overwrite` only when replacing an existing value is explicitly intended.

## 8. Permissions and authorization

Use the existing Nimbly authorization mechanisms.

Validate input before accepting a route or request.

Use:

```php
router_accept()
router_deny()
```

where appropriate.

Apply authorization before exposing protected data or performing protected actions.

Reuse existing roles, permissions, approval flows, and project authorization mechanisms instead of introducing parallel permission systems.

Do not confuse an approval record with executable capability. If an approved action lacks an actual implementation or registered execution path, treat that as a missing capability rather than weakening authorization boundaries.

## 9. PHP conventions

Use snake_case for PHP:

* functions,
* variables,
* parameters,
* and file names.

Do not use camelCase or PascalCase for ordinary PHP application code.

Keep functions focused.

Shortcodes should coordinate data and rendering rather than contain large amounts of business logic or inline markup.

Prefer proper fixes over hacks. Correct the underlying layout, field type, data flow, or integration issue rather than hiding its symptom.

## 10. Plan narrowly

Before implementation:

* inspect only relevant code,
* search narrowly,
* reuse findings already established in the current session,
* and keep one focused objective per session where practical.

Exclude generated files, dependencies, runtime data, build output, and large unrelated directories unless directly relevant.

Do not repeatedly inspect the repository simply to reassure yourself that nothing was missed.

Do not read the complete `NIMBLY.md` to begin a task, understand Nimbly generally, search for a capability, or make sure nothing was overlooked.

Use targeted documentation retrieval instead.

## 11. Implement one logical step at a time

Keep each change focused.

Follow established Nimbly and project patterns.

Do not silently broaden scope after implementation begins.

If the approved plan says not to implement a related issue, leave it untouched and mention it in the handoff.

Preserve unrelated local work.

## 12. Test efficiently

Run the smallest relevant verification first.

Common commands include:

```bash
./nimbly test:architecture --strict
./nimbly build
./nimbly test:run
```

Use only those relevant to the actual change.

For targeted PHP changes, focused syntax or project-specific tests may be more appropriate than a complete suite.

Prefer targeted CLI, Git, and `curl` checks for:

* routing,
* authentication reachability,
* branch state,
* migrations,
* synchronization,
* deployment state,
* and backend behavior.

Do not run browser tests for presentation-only changes unless visual or browser behavior genuinely needs verification.

Use Playwright when browser interaction itself is under test, for example:

* forms,
* inline editing,
* media pickers,
* Alpine behavior,
* responsive behavior,
* or concrete visual regressions.

Run full suites only when justified by the change.

Do not rerun an unchanged failing command. Diagnose the failure or change something first.

Use timeouts for commands that may hang.

Do not execute production maintenance merely to verify application code.

## 13. Keep context and output small

Context usage matters.

* Keep command output focused.
* Avoid dumping hundreds or thousands of successful lines into the conversation.
* Prefer `rg`, `head`, `tail`, targeted `sed` ranges, quiet flags, and focused test specifications.
* Redirect verbose output to temporary files when useful and inspect only relevant sections.
* Avoid reading complete logs, compiled assets, dependencies, generated files, large datasets, or complete documentation unless necessary.
* Do not repeat inspection already performed in the same session.

Check `/status` before major phases and after unexpectedly expensive work.

If a small task consumes an unexpectedly large part of the five-hour allowance, stop and provide a concise handoff.

If context becomes large, stop before starting another major phase and continue in a fresh session.

Use a fresh session for a separate substantial implementation, migration, or deployment phase.

## 14. Local environment

Confirm the local environment is available before browser or runtime verification.

When appropriate:

```bash
./nimbly up
```

Do not restart or rebuild the environment unnecessarily when it is already running.

## 15. Migration bookkeeping

For Nimbly 1.1 project migrations:

* Reconcile the Intra project record before considering the migration complete.
* Projects are expected to use current `master` Core unless a temporary exception is documented in project notes.
* Book two hours to the migrated project for migration and production verification, reusing or normalizing an existing booking rather than creating duplicates.
* Migrate legacy SMTP or `.services` mail configuration to Resend.
* Reuse established Resend credentials where appropriate.
* Keep credentials only in runtime `.env` files.
* Set `MAIL_FROM_NAME` to the project site name.
* Verify obsolete SMTP variables or tracked service credentials are removed.

## 16. Commit completed work

Commit only after the logical step is implemented and relevant checks pass.

Use Conventional Commits.

Commit messages should be:

* short,
* specific,
* professional,
* and normally one line.

Do not add `Co-Authored-By` or similar commercial/tooling noise.

Do not narrate vulnerabilities, exploit details, or internal failures in commit messages. Describe the change rather than documenting how something was exploitable.

## 17. Git restrictions

Never push to a remote branch unless explicitly instructed.

Never create, merge, or close pull requests unless explicitly instructed.

Never run destructive Git operations such as:

```text
reset --hard
clean
forced push
rebase
branch deletion
```

unless explicitly instructed.

Do not discard, overwrite, or remove existing local changes without explicit authorization.

## 18. Final handoff

When the task is complete:

* summarize what changed,
* list commits created,
* list relevant tests and checks performed,
* mention anything not completed or not verified,
* and explicitly identify issues that were diagnosed but intentionally left outside scope.

Compare the finished result with the requested outcome, not merely with whether the code compiles.

## At a glance

* Start from current `main` or `master`, including newer published live changes.
* `ext/` is a separate Git repository.
* Use `core/` only for functionality every Nimbly application needs.
* Inspect existing implementation before designing.
* Reuse Nimbly building blocks before adding custom architecture.
* Keep templates, libraries, modules, and resources in their proper roles.
* Keep HTML out of PHP libraries when markup belongs in templates.
* Static routes do not use `route.inc`.
* `[#if#]` is always self-closing.
* `[#set#]` does not overwrite by default.
* Use existing permissions and authorization mechanisms.
* Use snake_case in PHP.
* Run the smallest relevant tests.
* Keep command output and context small.
* Use targeted Nimbly documentation instead of reading `NIMBLY.md` in full.
* Stop rather than silently expanding a task into new architecture.
* Never push or perform destructive Git actions without explicit permission.
