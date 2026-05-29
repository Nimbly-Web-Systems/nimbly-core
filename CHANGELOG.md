# Changelog

## [1.1.0] — 2026-05-27

### Added
- Automatic resource indexes — field lookups via `.index/` subdirectory, no manual index management
- `index:rebuild` CLI command to regenerate indexes for a resource
- `system:upgrade-11` CLI command — automated migration from 1.0.0 to 1.1.0
- Resource `.meta` events system replacing global trigger handlers (`data-create` hooks removed)
- Email delivery via `.env` configuration (`MAIL_SERVICE`, `MAIL_FROM`, `RESEND_API_KEY`, etc.)
- Resend support as the recommended email provider alongside SMTP/PHPMailer
- Password reset flow enqueued as a background job instead of synchronous SMTP
- `docker:init` CLI command — generates Dockerfile and GitHub Actions workflow in `ext/`
- `schedule:publish` CLI command to copy core schedule defaults to `ext/cli/schedule.inc`
- `jobs:prune` CLI command to clean up completed jobs older than N days
- Tailwind CSS 4 and DaisyUI 5 — upgraded from Tailwind 3 / DaisyUI 4
- Alpine.js 3 integration
- Multi-language support built into the resource and admin layers
- Inline admin editing via medium-editor
- `[#get-img-html#]` shortcode with WebP/srcset/lazy loading and anti-upscale guards
- `splitdir` resource option for filesystem performance above ~10,000 records
- Node.js CLI runner (`./nimbly`) wrapping init, deps, build, and up commands

### Changed
- UUIDs are always stable random identifiers — never derived from field values (`pk` pattern removed)
- Slug routing uses `data_read_index()` instead of `data_exists()` with a derived UUID
- `.services` resource removed — email and API credentials move to `.env`
- `data_update_pk()` removed
- Docker development environment restructured into `docker/dev/` and `docker/prod/`

### Removed
- `_dep_` legacy admin UI paths
- `name` field type (replace with `type: text` and a separate `slug` field)
- Automatic `data-create` global trigger handlers

### Upgrade
Run `./nimbly system:upgrade-11` to migrate a 1.0.0 project automatically. See [NIMBLY.md](NIMBLY.md) §19 for the full upgrade guide.

---

## [1.0.0] — initial release
