# Managed pages and navigation

This optional module lets editors publish page records without creating route files. Exact and dynamic code routes run first; managed pages are considered only before the final 404.

Enable it with application-owned configuration and declarations:

- `ext/data/.config/managed_pages` optionally adds application page types or overrides Core types in its `page_types` object.
- `ext/modules/managed-pages/url-areas.json` lists enabled and reserved path prefixes.
- `ext/modules/managed-pages/navigation-slots.json` declares editable menu slots and their maximum depth.

Create `pages` and `.navigation` resources using the schemas required by the application. Page resources should use `managed-pages` / `managed_pages_validate_record` as their custom validator and enable `write_lock`. Localized page records use `title`, `path`, `published`, `body`, `seo_title`, `seo_description`, and internally maintained `previous_paths` fields.

Core always provides `default`, rendered by `managed-page-default`. A page-type definition uses the type ID as its key and provides `name`, `description`, and `template`. The admin field can use `options_library: managed-pages` and `options_function: managed_pages_type_options` to derive its choices from the merged configuration. Application definitions replace a Core definition when they use the same ID.

For example, `ext/data/.config/managed_pages` can add a landing page:

```json
{
  "page_types": {
    "landing": {
      "name": "Landing page",
      "description": "A promotional page with an application-specific layout.",
      "template": "page-landing"
    }
  },
  "uuid": "managed_pages"
}
```

Its templates live at `ext/tpl/page-landing/index.tpl` and, when using the shared HTML shell, `ext/tpl/page-landing-main/index.tpl`.

The declared template renders the full page. When a companion `<template>-main` exists, it supplies the HTML shell's main region. The resolver exposes the record as `page`, including `page.uuid`, and prepares canonical and language-switch metadata.

Use `[#managed-navigation slot=main var=main_navigation#]` to load a public, normalized tree. Page references follow current localized addresses. Unpublished or missing targets and their branches are omitted. Rendering remains application-owned.

Editors with `edit-.navigation` can use `/nb-admin/navigation`. Saves replace the complete tree and require its current revision, so stale edits are rejected. Run `./nimbly pages:check` before release to check declarations, missing templates, invalid addresses, duplicate claims, and collisions with code routes.
