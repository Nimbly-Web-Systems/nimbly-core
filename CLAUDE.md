See [NIMBLY.md](NIMBLY.md) for the complete Nimbly implementation reference.

At a glance:

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
- Never add `route.inc` to a static route (one with no `(param)` URL segments).
  `route.inc` exists only for dynamic routes that need to call `router_accept()`
  or `router_deny()`. Adding it to a static route causes a 404.
- **`[#if#]` has no block form — ever.** There is no `[#if#]…[#/if#]` syntax.
  `[#if#]` is always a single self-closing tag; conditional content lives in a
  separate template via `tpl=`. This is by design: templates contain no business
  logic. Never write block-style conditionals in templates.
- **`[#set#]` does not overwrite by default.** Without the `overwrite` param a
  `[#set#]` on an already-set variable is a no-op. Route templates can therefore
  set page variables early and core/shared templates act as fallbacks. Use
  `overwrite` only when you explicitly need to replace an existing value (e.g.
  passing data into a reusable template component).

Commit messages must follow the Conventional Commits style documented in
`NIMBLY.md`: short, specific, professional, and usually one line.
