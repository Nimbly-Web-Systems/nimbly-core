# Nimbly Quickstart

This is the short entry point for working on a Nimbly project. [`NIMBLY.md`](NIMBLY.md) remains the complete authoritative implementation reference.

## 1. Choose the repository

Nimbly combines two independent repositories:

- `core/` is the reusable framework. Framework features that every Nimbly app needs belong here.
- `ext/` is the application. Put project routes, data, templates, libraries, modules and theme configuration here.

Run `git status` for core and `git -C ext status` for the application. Do not put project customizations in core.

## 2. Build pages from routes and templates

Folders in `ext/uri/` map to URLs. A static route uses `index.tpl`. A dynamic route contains a parenthesized segment such as `ext/uri/articles/(slug)/`; it needs `route.inc` for acceptance or denial and a matching `.routes` record. Run `./nimbly routes:sync` after adding a dynamic route. Static routes must not have `route.inc`.

Reusable markup belongs in `ext/tpl/`. Keep data preparation and business logic in PHP libraries under `ext/lib/` or `ext/modules/`; templates render the prepared values. `[#if#]` is a self-closing shortcode with a `tpl=` target, never a block.

## 3. Define resources in `.meta`

Application resources live in `ext/data/<resource>/`. The `.meta` file defines fields, validation, indexes, permissions-related settings and lifecycle events. Records are JSON files identified by immutable UUIDs. Use indexed fields, such as a `slug`, for lookups in dynamic routes. Keep uploads and generated runtime data in the established ignored resources.

## 4. Use permissions at the boundary

Protect dynamic routes with `router_accept()` or `router_deny()` after validating route input. Use Nimbly’s roles and feature permissions for admin, API and resource access. Check authorization before loading or changing protected data, and keep user-facing strings in core translations with `[#text Key#]`.

## 5. Test, build and deploy

Use the smallest relevant check first:

```bash
./nimbly docs:list
./nimbly test:architecture --strict
./nimbly build
./nimbly test:run
```

Use `./nimbly up` for the local Docker environment. Deployments should run setup, asset build, lint and the relevant tests from the same checkout. Docker deployments use generated files from `./nimbly docker:init`; manual VPS deployments use the documented scheduler and environment configuration.

## 6. Find the detailed rule

Use the local documentation lookup commands before opening the full reference:

```bash
./nimbly docs:section "Template Syntax"
./nimbly docs:section "Template Syntax > Route templates"
./nimbly docs:search "router_accept"
```

Lookup is read-only and reads only the local `NIMBLY.md`.
