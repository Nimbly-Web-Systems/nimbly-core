# Managed pages and navigation

This optional module lets editors publish page records without creating route files. Exact and dynamic code routes run first; managed pages are considered only before the final 404.

Enable it with application-owned configuration in `ext/data/.config/managed_pages`:

- `enabled` turns the feature on; `enabled_page_types` limits the types available for new pages; `page_types` adds application page types or overrides Core types.
- `url_areas` lists enabled and reserved path prefixes (`enabled`, `reserved`, `include_site_languages`, `allow_unprefixed`).
- `navigation_slots` declares the editable menus as `{"main": {"name": "Main navigation", "depth": 2}}`. A menu shows on the site only where a template outputs it with `[#managed-navigation slot=main#]`, so adding one is a developer step.

Only users with `manage-system` may change this record through the web or API; editors use the feature but cannot change how it is set up. Older projects may still keep `url-areas.json` and `navigation-slots.json` in `ext/modules/managed-pages/`; they are read only when the matching key is missing from the record.

Create `pages` and `.navigation` resources using the schemas required by the application. Page resources should use `managed-pages` / `managed_pages_validate_record` as their custom validator and enable `write_lock`. Localized page records use `title`, `path`, `published`, `body`, `seo_title`, `seo_description`, and internally maintained `previous_paths` fields.

Core always provides `default`, rendered by `managed-page-default`. A page-type definition uses the type ID as its key and provides `name`, `description`, and `template`. The admin field can use `options_library: managed-pages` and `options_function: managed_pages_type_options` to derive its choices from the merged configuration. Application definitions replace a Core definition when they use the same ID.

Custom pages are disabled by default. Set `enabled` to `true` in
`.config/managed_pages` (admins can use the switch on the Pages tab) to show Pages in the admin resource menu, allow page
creation, and enable the public router fallback. Disabling it preserves page
records but stops their public addresses from resolving.

An optional `enabled_page_types` list in `.config/managed_pages` limits which
registered types may be used for new pages. Omitting it enables every registered
type; an empty list disables page creation. Existing pages continue to resolve
and edit with their registered type.

Applications may set `"include_site_languages": true` in `url_areas` to
add the configured `.config/site.languages` to the explicitly enabled URL
prefixes. Without the option, the declaration remains fixed. A resource schema
may likewise set `"languages": "site"` to resolve its authoring languages from
the site configuration; explicit language arrays retain their existing behavior.

To keep the Pages overview, add and edit screens inside the admin tab bar, add `"admin_tab": "pages"` (and an `"admin_subtitle"` line, so the tab bar sits at the same height as on the other tabs) to the `pages` resource's `.meta`. Adding `"admin_panel": {"template": "managed-pages-toggle", "feature": "manage-system"}` shows the custom-pages panel above the overview to admins only: the on/off switch and the page types editors may use. While custom pages are off, the Pages and Navigation tabs are hidden from everyone except users with `manage-system`.

Pages normally live under their language (`nl/zomer`). Set `"allow_unprefixed": true` in `url_areas` to let editors also publish a page without a prefix (`zomer`); the page keeps the language it was authored in, and the path must be unique across all languages and outside the `reserved` list. Without the option, single-language sites allow unprefixed pages and multi-language sites do not. The path field then offers a "Publish without a language prefix" choice on multi-language sites.

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

Unpublished addresses remain unavailable to anonymous visitors. Editors with
`edit-pages` may open them as previews and receive an unpublished-page warning.
Add `managed-page-preview-action` to the page resource's `record_actions` for a
preview button that follows the active editor language.

Use `[#managed-navigation slot=main var=main_navigation#]` to load a public, normalized tree. Page references follow current localized addresses. Unpublished or missing targets and their branches are omitted. Rendering remains application-owned.

Editors with `edit-.navigation` can use `/nb-admin/navigation`. The editor saves through the API (`PUT /api/v1/.navigation/<slot>-<language>`), so the standard `edit-.navigation` permission applies. Saves replace the complete tree and must send the `revision` they were loaded with; stale edits are rejected with `revision:stale`, and an invalid item is reported as `items.<item id>:<reason>` in the response `detail`.

The `.navigation` resource's `.meta` must enable `write_lock` and `upsert` and use `managed-navigation` / `managed_navigation_validate_record` as its custom validator. Run `./nimbly pages:check` before release to check declarations, missing templates, invalid addresses, duplicate claims, and collisions with code routes.
