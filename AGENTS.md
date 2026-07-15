Nimbly is a full-stack design system. It is required to read
[NIMBLY.md](NIMBLY.md), the complete Nimbly implementation reference, once per
session before making any significant change. After that, consult only the
sections relevant to the current task.

## Workflow

For every task, follow this workflow unless explicitly instructed otherwise.

1. Start from `main` or `master` with the latest live changes merged in.
   - Development must happen on the main development branch, usually `main` or `master`.
   - Check the current branch and working tree status.
   - Fetch the latest remote changes.
   - Update the local `main` or `master` branch.
   - Check whether the live or production branch has changes that are not yet in `main` or `master`.
   - If live has newer changes, merge those live changes into `main` or `master` before editing.
   - Do not develop directly on the live or production branch.
   - Remember that `ext/` is a separate Git repository. Application changes use Git inside `ext/`.

2. Decide core vs. ext before touching files.
   - Ask: would every Nimbly app need this, not just this project? If yes, it is framework work and belongs in `core/`. If it is this project's own resources, routes, business logic, or configuration, it belongs in `ext/`.
   - See "Deciding core vs. ext" in `NIMBLY.md` for the full test and a worked example, including the role editor pages that were originally stranded in `ext/`.
   - If genuinely ambiguous, ask rather than defaulting to `ext/`.

3. Plan the work.
   - Inspect the relevant code first.
   - Consult only the relevant sections of `NIMBLY.md` after the required initial read.
   - Break the task into small, logical steps.
   - Do not start with broad rewrites.
   - Narrow searches to the relevant repository, directories, file types, and patterns.
   - Exclude generated files, dependencies, runtime data, and build output unless directly relevant.
   - Do not repeat repository inspection already completed in the same session. Reuse established findings.
   - Prefer one focused objective per session.

4. Implement one logical step.
   - Keep the change focused.
   - Prefer existing Nimbly building blocks.
   - Follow the conventions in `NIMBLY.md`.

5. Sanity test the step.
   - Run relevant local checks.
   - Be efficient with verification time and tokens:
     - Confirm the local environment is running before browser tests. If needed, ask the user to run `./nimbly up`, or run it yourself only when server startup is part of the task.
     - Prefer targeted CLI, `curl`, and Git checks for health, routing, authentication reachability, branch or tag state, migrations, and deployment verification.
     - For presentation-only changes such as spacing, utility classes, copy, labels, or simple static links, do not run Playwright or the full test suite by default. Verify with the diff, relevant lint or template checks, an asset build when required, and a focused visual inspection only when it adds value.
     - Use Playwright only when browser behavior is genuinely under test, such as admin forms, inline editing, media picker behavior, Alpine interactions, or responsive and visual regressions.
     - Reserve browser and full-suite testing for changes involving interaction, permissions, data flow, business logic, or a concrete regression risk.
     - Run the smallest relevant test first.
     - Run the full suite only after focused checks pass, normally near completion.
     - Avoid repeated full E2E runs after infrastructure or setup failures. Fix or confirm the environment first, then rerun the smallest relevant specification.
     - Do not rerun an unchanged failing command. Diagnose the failure or change something first.
     - Use timeouts for commands that may hang and stop unnecessary background processes after verification.
   - Context and output discipline:
     - Keep displayed command output below roughly 200 lines unless more is genuinely necessary.
     - Redirect verbose build and test output to a temporary file, then inspect only failures, warnings, relevant excerpts, and the final summary.
     - Do not stream thousands of successful test or build lines into the conversation.
     - Prefer quiet flags, targeted specifications, `rg`, `head`, `tail`, and focused `sed` ranges over complete output.
     - Avoid reading generated assets, compiled files, dependency trees, large data directories, and complete logs unless specifically required.
     - After completing a major phase, summarize the current state before beginning another large phase.
     - Check `/status` before a major phase and after unusually expensive work.
     - If a small task consumes an unexpectedly large part of the five hour limit, stop and provide a concise handoff.
     - When the session context becomes large, stop before beginning another major phase and provide a concise handoff for a fresh session.
     - Start a fresh session before a separate major implementation, migration, or deployment phase.
   - Compare the result against the requested outcome, not only against whether the code compiles.

6. Adjust until correct.
   - If the sanity test or visual result is not correct, fix it before moving on.
   - Rerun the relevant check after adjustments.

7. Commit the completed step.
   - Commit only after the step is implemented and sanity tested.
   - Use Conventional Commits.
   - Keep commit messages short, specific, professional, and usually one line.
   - Do not add commercial noise such as `Co-Authored-By`.
   - Do not narrate bugs, vulnerabilities, or internal shortcomings in commit messages. Describe what changed, not how something was broken or exploitable. This is open source history, so do not hand future readers an exploit writeup.

8. Repeat.
   - Continue step by step until the task is complete.

9. Final handoff.
   - Summarize what changed.
   - List commits created.
   - List tests and checks performed.
   - Mention anything not completed or not verified.

## Restrictions

- Never push to a remote branch unless explicitly instructed.
- Never create, merge, or close pull requests unless explicitly instructed.
- Never run destructive Git commands such as `reset --hard`, `clean`, forced push, rebase, or branch deletion unless explicitly instructed.
- Do not discard, overwrite, or remove existing local changes unless explicitly instructed.
- Do not modify `core/` unless the task is explicitly framework work. The test is whether every Nimbly app would need the change, not just this project. See "Deciding core vs. ext" in `NIMBLY.md`.

## At a glance

- **`ext/` is a separate Git repository.** Always run Git commands inside `ext/` for application changes, for example `git -C ext status`. The project root is the core repository and knows nothing about `ext/` changes.
- Work in `ext/` for project customizations. Work in `core/` for framework work, meaning anything every Nimbly app would need, not just this one. Do not default to `ext/` merely because it looks safer.
- Follow the PHP naming convention documented in `NIMBLY.md`: snake_case everywhere for functions, variables, parameters, and file names. Do not use camelCase or PascalCase in PHP.
- Prefer proper fixes over hacks. If a layout, field type, or data flow is wrong, fix the underlying issue rather than hiding the symptom.
- Use existing Nimbly building blocks first: core libraries, templates, and established shortcodes before adding custom code.
- Build self contained UI features as reusable components in `ext/tpl/<name>/` or focused custom shortcodes in `ext/lib/<name>/`, not as inline page code.
- Keep shortcode functions short. They should coordinate data and rendering, with logic and layout separated into libraries and templates where possible.
- Never add `route.inc` to a static route, meaning one with no `(param)` URL segments. `route.inc` exists only for dynamic routes that need to call `router_accept()` or `router_deny()`. Adding it to a static route causes a 404.
- **`[#if#]` has no block form, ever.** There is no `[#if#]…[#/if#]` syntax. `[#if#]` is always a single self closing tag. Conditional content lives in a separate template via `tpl=`. This is by design because templates contain no business logic. Never write block style conditionals in templates.
- **`[#set#]` does not overwrite by default.** Without the `overwrite` parameter, a `[#set#]` on an already set variable is a no op. Route templates can therefore set page variables early and core or shared templates act as fallbacks. Use `overwrite` only when you explicitly need to replace an existing value, for example when passing data into a reusable template component.

Commit messages must follow the Conventional Commits style documented in
`NIMBLY.md`: short, specific, professional, and usually one line.
