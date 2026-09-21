# Managed pages and navigation

This optional module lets editors publish page records without creating route files. Exact and dynamic code routes run first; managed pages are considered only before the final 404.

Enable it with three application-owned declarations under `ext/modules/managed-pages/`:

- `page-types.json` maps stable type IDs to editor labels and approved template names.
- `url-areas.json` lists enabled and reserved path prefixes.
- `navigation-slots.json` declares editable menu slots and their maximum depth.

Create `pages` and `.navigation` resources using the schemas required by the application. Page resources should use `managed-pages` / `managed_pages_validate_record` as their custom validator and enable `write_lock`. Localized page records use `title`, `path`, `published`, `body`, `seo_title`, `seo_description`, and internally maintained `previous_paths` fields.

A page type named `standard` with template `page-standard` renders that template for the full page and, when present, uses `page-standard-main` for the HTML shell's main region. The resolver exposes the record as `page`, including `page.uuid`, and prepares canonical and language-switch metadata.

Use `[#managed-navigation slot=main var=main_navigation#]` to load a public, normalized tree. Page references follow current localized addresses. Unpublished or missing targets and their branches are omitted. Rendering remains application-owned.

Editors with `edit-.navigation` can use `/nb-admin/navigation`. Saves replace the complete tree and require its current revision, so stale edits are rejected. Run `./nimbly pages:check` before release to check declarations, missing templates, invalid addresses, duplicate claims, and collisions with code routes.
