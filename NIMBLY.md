# Nimbly — Implementation Reference

This document is the authoritative reference for implementing features in Nimbly. It is intended for AI agents and developers working on Nimbly projects. Follow these conventions exactly.

---

## 1. Project Structure

### Two repositories, one runtime

Nimbly splits every project into two separate git repositories that live nested on disk:

| Repository | What it is | Who owns it |
|---|---|---|
| **core** | The Nimbly framework — routing, template engine, shortcodes, admin, API | Nimbly (never modified per project) |
| **ext** | Your application — routes, templates, data, custom shortcodes | You (every project has its own ext repo) |

On disk they combine into one directory tree:

```
my-project/          ← core repo root
  core/              # Framework internals
  ext/               ← ext repo root (nested inside core)
  css/               # CSS source (shared build)
  js/                # JS source (shared build)
```

This means `git status` at the project root reflects core changes; `git status` inside `ext/` reflects application changes. They are independent repos with independent histories, branches, and remotes.

### Setting up a new project

```bash
# 1. Clone core
git clone git@github.com:Nimbly-Web-Systems/nimbly-core.git my-project
cd my-project

# 2. Clone your application into ext/
git clone git@github.com:your-org/your-app.git ext

# 3. Install dependencies with Node 20+ and build
bash -i -c "nvm use --lts && npm install && npm run build"
```

After this, the project is fully operational. Core and ext evolve independently.

### Updating core or ext from the admin

Both repos can be updated without touching the terminal. In the admin (`/nb-admin/`), navigate to **Settings** — there are separate **Update Core** and **Update Ext** buttons that run `git pull` on the respective repository. This is the standard way to deploy updates in production.

### Directory layout

```
core/          # Framework — never modify
  lib/         # Core libraries and shortcode implementations
  modules/     # Core modules (admin, forms, api, install, user)
  tpl/         # Core templates (html wrapper, etc.)
  uri/         # Core routes
ext/           # Your application — all custom work goes here
  data/        # Resources (.meta + JSON records)
  lib/         # Custom libraries and shortcode implementations
  modules/     # Custom modules
  tpl/         # Reusable templates
  uri/         # Route templates
  static/      # Built assets (output of npm build)
css/           # CSS source
js/            # JS source (JSX/Alpine)
```

**Rule:** Never modify `core/`. All customization lives in `ext/`.

**Rule:** All text in `core/` must be in **English**. Use `[#text Key#]` for every user-facing string so projects can supply translations via `ext/data/.i18n/text.<lang>.po`. Never hard-code Dutch or any other language in core templates.

### Project context

Nimbly projects may include project-specific context for developers and AI agents.

| Path | Purpose | Git behavior |
|---|---|---|
| `.context/` | Local/private checkout context: requirements notes, design PDFs, screenshots, research, meeting notes, client material, and other background used while working locally. | Belongs to the core checkout but is local-only by default. Only housekeeping files such as `.context/readme.md` and `.context/.gitignore` should be committed. |
| `ext/.context/` | Shared application context: requirements, design notes, implementation decisions, or project references that should deliberately travel with the application repo. | Belongs to the nested ext repo and may be committed when the information is safe and useful for everyone working on that application. |

Use `.context/` for private/operator/client-specific material. Use `ext/.context/` only for shared project knowledge that should be part of the application history.

---

## Frontend Stack

The frontend (both admin and public) uses **Tailwind CSS 4**, **DaisyUI 5**, and **Alpine.js** exclusively.

- **Do not use jQuery** or any other JavaScript library/framework.
- Interactivity is handled with Alpine.js (`x-data`, `x-bind`, `@event`, etc.).
- CSS is written with Tailwind utility classes. Component styles use DaisyUI.

This constraint applies to all templates in `core/` and `ext/`.

### Theme colors

Project-specific Nimbly theme colors and DaisyUI theme values are defined in `ext/tailwind.theme.js`.

This file exports the default Tailwind theme extension and a named `daisyuiThemes` export. Together they control the project color tokens used by Nimbly UI surfaces such as the Nimbly bar, admin controls, buttons, links, and related DaisyUI/Tailwind styling. Core keeps this JavaScript theme file as the project customization API while building with Tailwind CSS 4 and DaisyUI 5. Update colors there when matching a project design.

Use `ext/theme.css` for project-specific public-site CSS and component overrides. Do not use `ext/theme.css` as the primary place to redefine Nimbly/admin theme colors unless a narrow component override is required.

**Always use theme colors instead of inventing colors.** Reach for the project's named tokens first — `primary`, `cnormal`, `clight`, `cdark`, `cdarkest`, `cbar`, `clink`, `secondary` — before using any Tailwind palette color (`red-700`, `blue-500`, etc.). Hard-coded palette colors bypass the theme and make redesigns harder. Only use a raw palette color when no theme token fits semantically and adding one to `ext/tailwind.theme.js` is not warranted.

### Frontend-first data loading

When dynamic data needs to be displayed or interacted with in the frontend, prefer an Alpine.js solution over a backend template loop. Use `[#fmt var=data.records json#]` to pass server-loaded data into Alpine.js as a JSON object, then render it reactively.

```html
<div x-data='{ records: [#fmt var=data.articles json empty=[]#] }'>
    <template x-for="item in records" :key="item.uuid">
        <div x-text="item.title"></div>
    </template>
</div>
```

Weigh the tradeoff carefully, especially when dynamic data is involved:

| Prefer frontend (Alpine.js + `[#fmt json#]`) | Prefer backend (template loop) |
|---|---|
| Interactive filtering, sorting, real-time updates | Static content that must be SEO-indexed |
| Data that changes without a page reload | Simple lists with no interactivity |
| Data already loaded by `[#data#]` — formatting to JSON costs nothing | Per-record server-side access control |

If the data is already loaded with `[#data#]`, passing it to Alpine.js is free and keeps the template simpler. Default to the frontend approach when both options work equally well.

### KISS and YAGNI

Keep implementations as small as the current requirement allows.

- Prefer the simplest solution that fully solves the actual task.
- Do not add speculative fields, statuses, workflow steps, or audit data "for later".
- Reuse built-in Nimbly metadata like `_created` and `_modified` before introducing parallel custom fields.
- Do not derive UUIDs from business fields unless the project explicitly requires it.
- When duplicate prevention is needed, prefer an index on the business field before inventing a more complex identity scheme.

Production-ready does **not** mean speculative. It means clean, coherent, and sufficient for the current use case.

- If a project-specific solution exposes a reusable framework capability, explicitly mention the core/framework option before defaulting to a local workaround.

### Frontend forms

For simple public forms, prefer Alpine.js with `nb.api.post()` or `build-form` over route `post_*.inc`.

- Normalize values in the frontend when the stored value should be canonical, for example `trim().toLowerCase()` for email.
- If duplicate submissions should be treated as success in the UX, map `RESOURCE_EXISTS` to the same success state as `RESOURCE_CREATED`.

---

## 2. Template Syntax

Templates use shortcodes enclosed in `[#` and `#]`.

```
[#shortcode#]
[#shortcode param#]
[#shortcode key=value key2=value2#]
```

Shortcodes can be nested:

```
[#get data.users.[#get selected-id#]#]
```

Templates are plain files in the target output format (HTML, JSON, CSS, etc.). They have a `.tpl` extension.

### Route templates

Every URL maps to a file in `ext/uri/`:

```
ext/uri/index.tpl              → /
ext/uri/about/index.tpl        → /about/
ext/uri/blog/(slug)/index.tpl  → /blog/<anything>/
```

A route template renders the page:

```
[#html#]
```

The `main.tpl` in the same folder contains the page body, rendered inside the HTML shell. It is always scoped to that route — even `ext/uri/main.tpl` is only the body for the home page (`/`), not a global layout.

### Reusable templates

Stored in `ext/tpl/<name>/index.tpl`. Called with their name as a shortcode:

```
[#hero-section#]
```

Template components do **not** receive parameters directly. To pass data to a template, use `[#set ... overwrite#]` before calling it:

```
[#set back_url=/agenda overwrite#]
[#back-button#]
```

Inside the template, read the variable with `[#get back_url default=/#]`. Lib shortcodes (`ext/lib/<name>/`) are different — they receive `$params` and can be called with inline parameters.

### URI-scoped templates

Templates can also live flat inside a URI folder (not in a subfolder). These are not routes — they are partial templates included from within that route's `index.tpl` or `main.tpl`.

```
ext/uri/new/page-settings-fields.tpl   ← partial, not a route
ext/uri/new/index.tpl                  ← route
ext/uri/new/main.tpl                   ← route body
```

Important: a subfolder inside `ext/uri/` **always** creates a new route. A flat `.tpl` file in a URI folder is a partial template scoped to that route.

### route.inc

`route.inc` is only used for **dynamic routes** — routes with `(param)` segments in the URL that need to be matched, validated, and accepted or rejected. Static routes (plain URL paths with no parameters) do not use `route.inc`; their `index.tpl` is loaded directly.

A `route.inc` sits alongside `index.tpl` and decides whether this route owns the request:

```php
<?php
$parts = router_match(__FILE__);
if ($parts === false) return;

$slug = $parts[0];
load_library('data');
load_library('md5');

$records = data_read_index('articles', 'title_slug', md5_uuid($slug));
if (empty($records)) return;

set_variable_dot('record', reset($records));
router_accept();
```

#### Router functions

| Function | Description |
|---|---|
| `router_match(__FILE__)` | Matches the current URL against this route's dynamic segments. Returns an array of captured values (one per `(param)` in the path), or `false` if the URL does not match. Always call first. |
| `router_accept()` | Signals that this route.inc accepts the request. The template engine proceeds to render `index.tpl`. |
| `router_deny()` | Signals rejection — the router continues looking for another matching route. Calling `return` without `router_accept()` has the same effect. |

The standard pattern: call `router_match()`, validate the result, load and validate any data, then call `router_accept()` only when everything checks out. If anything fails, just `return` — the request falls through to the next route or a 404.

Keep `route.inc` focused on routing logic only — match, validate, set a variable or two, accept or deny. Business logic belongs in a library loaded from `index.tpl` via a shortcode.

> **Never add `route.inc` to a static route.** A static route (e.g. `login/`, `about/`) has no `(param)` segments and is always accepted — adding `route.inc` without calling `router_accept()` causes a 404. If you need to run logic on a static page, put it in a shortcode called from `index.tpl`, not in `route.inc`.

---

## 3. Core Shortcode Reference

### Data

#### `[#data resource#]`
Loads all records of a resource into `data.<resource>`.

```
[#data articles#]
[#data articles sort=date|desc#]
[#data articles filter=published:yes#]
[#data articles search=nimbly#]
[#data articles var=featured filter=featured:yes#]
[#data users.abc123#]           → loads single record into data.users.abc123
[#data users uuid=abc123#]      → same
```

Parameters:
- `sort` — `field|asc`, `field|desc`, multiple: `date|desc,title|asc`
- `filter` — `field:value`, multiple: `published:yes,status:new`, negation: `status:!draft`, or: `status:new||todo`
- `search` — full-text search across all fields
- `var` — custom variable name instead of `data.<resource>`
- `op` — `read` (default) or `list` (UUIDs only)

#### `[#repeat data.articles#]`
Iterates over a data variable, rendering the template with the same name for each item.

```
[#repeat data.articles#]
[#repeat data.articles tpl=article-card#]
[#repeat data.articles limit=3#]
[#repeat data.articles filter=published:yes#]
[#repeat data.articles var=post#]   → item variable named "post" instead of "item"
```

Inside the template, each record's fields are available as `item.<field>`:

```html
<h2>[#get item.title#]</h2>
<p>[#get item.intro#]</p>
```

Also available per iteration:
- `item.ix` — numeric index (0-based)
- `item.x` — record key
- `item.key` — record key (string)

#### `[#data-sort data.articles sort=title|string|asc#]`
Sorts an existing data variable in place.

```
[#data-sort data.articles sort=date|desc#]
[#data-sort data.articles sort=title|string|asc#]
[#data-sort data.articles sort=price|numeric|asc#]
```

#### `[#data-join a b#]`
Merges `data.a` and `data.b` into `data.join`. Each record gets a `resource_type` field set to its source resource name.

#### `[#data-count resource#]`
Outputs the number of records in a resource.

#### `[#lookup resource.uuid.field#]`
Reads a single field value from a record.

```
[#lookup users.abc123.name#]
[#lookup categories.[#get item.category#].title#]
[#lookup users.abc123.name empty="Unknown"#]
```

---

### Variables

#### `[#set key=value#]`
Sets a template variable.

```
[#set page-title="About us"#]
[#set active-nav=about#]
[#set body-classes=editor-role append#]    → appends with a space separator
[#set tags=news append=,#]                 → appends with a custom separator
[#set key=value session#]                  → persists in session
[#set key=value overwrite#]                → replaces value even if already set
```

The `append` param concatenates rather than sets. Without a separator value it
inserts a space between the existing value and the new one; supply a character
to use a different separator. Useful for building CSS class lists or
comma-separated strings:

```
[#set body-classes=site-body#]
[#set body-classes=editor-role append#]    → "site-body editor-role"
[#set body-classes=dark-mode append#]      → "site-body editor-role dark-mode"
```

**`[#set#]` does not overwrite by default.** If the variable already has a
value, a plain `[#set#]` is a no-op. This makes it easy to define fallback
values in shared/core templates while letting route templates override them
first. Only add `overwrite` when you explicitly want to replace an existing
value (e.g. when passing data into a reusable template component).

#### `[#get varname#]`
Outputs a variable's value.

```
[#get page-title#]
[#get item.title default="Untitled"#]
[#get language#]
```

#### `[#get-key varname key#]`
Returns a value from an array variable by key.

```
[#get-key data.settings theme#]
```

#### `[#fmt var=data.articles json#]`
Formats a variable for output.

```
[#fmt var=data.records json#]              → JSON encode
[#fmt var=myvar empty={} json#]            → JSON with fallback
[#fmt val=item.date type=date fmt="d-m-Y"#]
[#fmt val=item.body type=html#]            → strips tags
[#fmt val=item.size type=bytes#]           → human-readable bytes
[#fmt val=item.created type=ago#]          → "3 days ago"
[#fmt val=[#data-count users#] type=number round=-2 round_mode=floor#]
```

Types: `text`, `html`, `date`, `ago`, `json`, `bytes`, `number`, `boolean`, `image`, `password`

#### `[#count varname#]`
Outputs the number of items in an array variable.

```
[#count data.articles#]
```

---

### Flow control

#### `[#if condition action#]`

```
[#if user=(empty) redirect=login#]
[#if language=en tpl=intro-en#]
[#if status=published echo=yes#]
[#if user=(not-empty) tpl=dashboard tpl_else=login#]
[#if score=0 echo_else=[#get score#]#]
[#if not role=admin redirect=home#]
[#if lang=en or lang=nl tpl=western-layout#]
```

Conditions: `key=value`, `key=(empty)`, `key=(not-empty)`
Actions: `tpl=`, `tpl_else=`, `echo=`, `echo_else=`, `redirect=`
Modifiers: `not`, `or`, `and`

**`[#if#]` is always a single self-closing tag — there is no block form
(`[#if#]...[#/if#]`), and this is by design.** Templates contain no business
logic. Conditional content always lives in a separate template referenced by
`tpl=`. Never write block-style `[#if#]…[#/if#]` in a template.

#### `[#redirect url#]`
Redirects immediately.

```
[#redirect login#]
[#redirect [#base-url#]/dashboard#]
```

---

### URL and path

#### `[#base-url#]`
Returns the base URL path of the installation. Use this for all internal links.

```html
<a href="[#base-url#]/about">About</a>
<link href="[#base-url#]/ext/static/app.css?v=[#app-modified#]">
```

#### `[#base-path#]`
Returns the filesystem path to the installation root. For server-side file includes.

#### `[#url#]`
Returns the current full URL.

#### `[#is-url segment#]`
Sets `is-url` to `true` if the current URL starts with the given segment. Use for active nav states.

```
[#is-url about#]
[#if is-url=true echo=active#]

[#is-url (home) =#]   → exact match for homepage
```

#### `[#app-modified#]`
Returns a cache-busting version string for built assets.

```html
<link rel="stylesheet" href="[#base-url#]/ext/static/app.css?v=[#app-modified#]">
<script src="[#base-url#]/ext/static/app.js?v=[#app-modified#]"></script>
```

---

### Content

#### `[#get-html field#]`
Outputs an editable HTML content field. Supports inline admin editing.

```
[#get-html main_text#]
[#get-html content.home.intro default="<p>Edit this text</p>"#]
```

#### `[#get-i18n varname lang=en#]`
Returns the translated value of an i18n field for the given language. Defaults to the active language.

```
[#get-i18n item.title#]
[#get-i18n item.title lang=en#]
```

#### `[#text Label_key#]`
Outputs a translated UI label from `.po` files. Use underscores for spaces in the key.

```
[#text Search#]
[#text Save_changes#]
[#text No_results_found#]
```

Translations live in `ext/data/.i18n/text.<lang>.po`.

#### `[#markdown#]`
Renders Markdown content.

#### `[#cfield field#]`
Resolves the fully-qualified dot-path of a content field in the current template context. Used with `[#get-html#]` for static, inline-editable page content stored in the `.content` resource.

The `.content` resource is a key-value store for editable HTML blocks that are not tied to any data record. Each block is identified by `content.<page>.<field>`. When `[#get-html#]` targets a `.content` path that does not exist yet, it auto-creates the record and outputs the `default` value.

```html
<!-- In ext/tpl/about-intro/index.tpl -->
[#get-html content.about.intro default="<p>Edit this text</p>"#]
[#get-html content.about.body default="<p>Body text here</p>"#]
```

`[#cfield field#]` resolves the field path from the current template context, useful when the same template is used in multiple places:

```html
[#get-html [#cfield intro#] default="<p>Edit this</p>"#]
```

This pattern requires the user module to be active (loaded automatically).

#### `[#get-img-html image sizes="...#]`
Renders a responsive `<img>` tag with `srcset` and `sizes`.

```
[#get-img-html [#record.main_img#] sizes="xs-50,sm-33,lg-25"#]
```

Size tokens use breakpoint-percentage pairs (`lg-50` = 50% of viewport width at lg breakpoint). The module generates appropriately sized variants for all specified breakpoints.

---

### HTML page

#### `[#html#]`
Renders the full HTML page shell (doctype, head, body). Place at the end of route templates.

The following variables influence the HTML shell when set before `[#html#]`:

| Variable | Description |
|---|---|
| `page-title` | Sets the `<title>` tag and OG/Twitter title |
| `body-classes` | Adds CSS classes to the `<body>` tag |
| `page-settings-link` | Adds a shortcut link in the Nimbly admin bar pointing to the admin edit page for the current record. Use this on detail pages to allow quick admin access from the frontend. Example: `[#base-url#]/nb-admin/articles/[#record.uuid#]` |
| `page-description` | Overrides the site-wide meta description for this page. Falls back to the site config description. |
| `og-image` | Image for social sharing. Accepts a **UUID** (expanded to an absolute `/img/UUID/1200w` URL), a **relative path** (`img/og-card.png`), or an **absolute URL**. Falls back to the project default set in `ext/tpl/meta/index.tpl`. |
| `og-type` | OG type for this page. Defaults to `website`. Use `article` for content detail pages. |

```
[#set page-title="[#get record.title#]"#]
[#set og-type=article#]
[#set og-image=[#record.main_img#]#]
[#set page-description="[#fmt var=record.main_text type=html max_length=160#]"#]
[#set page-settings-link="[#base-url#]/nb-admin/event/[#record.uuid#]"#]
[#set body-classes=site-body#]
[#html#]
```

The project default OG image is set once in `ext/tpl/meta/index.tpl` without `overwrite`, so per-page values always take priority. Two image utilities are available as shortcodes:

- `[#img-url UUID-or-path-or-URL#]` — normalises any image reference to an absolute URL (useful outside meta, e.g. for JSON-LD)
- `[#first-img-uuid var=record.main_text#]` — extracts the UUID of the first embedded image from an HTML field; returns `(empty)` when none is found

---

### Utilities

#### `[#include file=path#]`
Includes a file server-side.

```
[#include file=[#base-path#]ext/tpl/head-scripts/index.tpl#]
```

#### `[#uuid#]`
Generates a new UUID.

#### `[#salt#]`
Generates a random salt string.

#### `[#md5 value#]`
Returns MD5 hash of a value.

#### `[#env KEY default=value#]`
Returns a value from `.env`, falling back to `default` when the key is missing.

```
[#env STRIPE_LINK_ANNUAL#]
[#env MAIL_DRIVER default=smtp#]
```

#### `[#logged-in#]`
Returns `"logged-in"` if a user session is active.

```
[#if logged-in=(empty) redirect=login#]
```

#### `[#feature-cond features=name#]`
Conditionally renders content based on whether the current user has a specific feature/permission.

```
[#feature-cond features=manage-content tpl=edit-button#]
[#feature-cond features=manage-content echo="<a href='/admin'>Admin</a>"#]
[#feature-cond features=manage-content tpl=admin-panel tpl_else=access-denied#]
[#feature-cond features=manage-content,view-reports tpl=dashboard#]
```

Parameters:
- `features` — comma-separated list of feature names; access is granted if the user has **any** of them, or has `(all)` (admin)
- `tpl` — template to render if access is granted
- `tpl_else` — template to render if access is denied
- `echo` — string to output if access is granted
- `echo_else` — string to output if access is denied

Features are assigned to roles in `/nb-admin/roles/`. The admin role always has `(all)` which bypasses all feature checks.

#### `[#date input fmt=Y-m-d#]`
Formats a date value. Input can be a Unix timestamp, a date string, or omitted (defaults to today).

```
[#date fmt=d-m-Y#]                        → today in d-m-Y format
[#date item.date fmt="j F Y"#]            → formats item.date
[#date 1741265734 fmt=Y-m-d#]             → formats Unix timestamp
```

Uses PHP date format strings.

#### `[#slug value#]`
Converts a string to a URL-safe slug (lowercase, Unicode-aware, hyphens for non-alphanumeric).

```
[#slug item.title#]
[#slug "Hello World!"#]   → hello-world
```

#### `[#strip value#]`
Strips all HTML tags from a value.

```
[#strip item.body#]
```

#### `[#int varname#]`
Returns the integer value of a variable.

```
[#int item.count#]
[#int var=score#]
```

#### `[#get-first dataset#]`
Stores the first item of a dataset into `first` (or a custom variable).

```
[#get-first data.articles#]
[#get first.title#]

[#get-first data.articles var=latest#]
[#get latest.date#]
```

#### `[#implode varname#]`
Joins an array variable into a string. For flat arrays returns a quoted, comma-separated list; for arrays of objects returns JSON.

```
[#implode item.tags sep=", "#]
```

#### `[#reverse-lookup resource value key#]`
Looks up a record in a loaded resource by field value and returns a different field. Useful for resolving labels from stored IDs.

```
[#reverse-lookup categories [#get item.category_id#] title#]
```

#### `[#rkey value#]`
Normalizes a string to a lowercase resource key (trims whitespace, lowercases).

```
[#rkey item.type#]
```

#### `[#last-update#]`
Returns a Unix timestamp of the most recently modified source file across `ext/` and `core/`. Useful with `[#fmt type=ago#]` to display when the site was last updated.

```
[#fmt val=[#last-update#] type=ago#]   → "3 days ago"
[#fmt val=[#last-update#] type=date fmt=d-m-Y#]
```

#### `[#obfuscate text#]`
Renders text using invisible Unicode characters and Alpine.js so it's invisible to bots/scrapers but readable by users. Use for email addresses and phone numbers.

```
[#obfuscate info@example.com#]
```

#### `[#host#]`
Returns the HTTP host name.

```
[#host#]   → example.com
```

#### `[#get-ip#]`
Returns the client IP address (respects `X-Forwarded-For` for proxied setups).

```
[#get-ip#]
```

#### `[#uri-path#]`
Returns the filesystem path of the current route's directory.

#### `[#url-key#]`
Returns a normalized string key for the current URL, useful for body classes or JS page detection.

```
[#set body-classes=[#url-key#]#]
```

`/admin/users/` → `admin_users`, homepage → `_home`.

#### `[#http-header type#]`
Outputs a response Content-Type header. Use at the top of non-HTML routes.

```
[#http-header json#]
[#http-header css#]
[#http-header csv#]
```

Types: `css`, `js`, `json`, `woff`, `csv`, `403`, `404`, `500`.

#### `[#system-messages#]`
Renders any queued system messages (set server-side via the session). Use in the HTML template or layout to display flash messages.

#### `[#empty-img#]`
Outputs a 1×1 transparent GIF as a data URI. Use as a placeholder `src` before a real image is set.

#### `[#max-upload-size#]`
Outputs the PHP `upload_max_filesize` value in human-readable form. Useful in upload form hints.

#### `[#json2post#]`
Parses a raw JSON request body into `$_POST`. Place at the top of API route templates that receive JSON payloads.

#### `[#unquote varname#]`
Outputs a variable's value with quotes HTML-escaped (`"` → `&quot;`, `'` → `&apos;`). Safe for embedding variable values inside HTML attribute strings.

```html
<div x-data='{"title": "[#unquote item.title#]"}'></div>
```

#### `[#collect-script path#]`
Defers a script include to be rendered together at the end of the page, avoiding duplicates. Call without arguments to render all collected scripts.

```
[#collect-script [#base-url#]/ext/static/chart.js#]
...
[#collect-script#]   ← renders all collected scripts here
```

#### `[#is-dev-env#]`
Outputs `DEV` or `PROD`. Useful for conditional debug output or environment-specific behaviour.

```
[#if [#is-dev-env#]=DEV tpl=debug-panel#]
```

#### `[#ipsum words=200#]`
Generates Lorem Ipsum placeholder text. For prototyping only.

```
[#ipsum words=100#]
[#ipsum words=50 format=html#]
```

#### `[#email config_id#]`
Legacy email helper. Existing projects may still use `.services`-based email configuration, but new work should avoid direct email sending inside HTTP requests. Prefer an env-backed mail transport and enqueue email jobs for background processing when the jobs runner is available.

#### `[#detect-language#]`
Returns the active language code. Detection order:
1. URL prefix (`/en/`, `/nl/`)
2. User preference (`?lang=en`)
3. Domain TLD (`.en`, `.nl`)
4. Browser language header
5. Fallback — first language defined in site config

#### `[#language#]`
Returns the currently active language code. Automatically set in the HTML template.

#### `[#debug#]`
Outputs debug information. Use during development only.

```
[#debug#]
[#debug variables session#]
```

#### `[#log message#]`
Writes to `ext/data/.tmp/logs/system.log`.

#### `[#nop#]`
No-op. Use to comment out shortcodes temporarily.

---

## 4. Resources

Resources are structured data collections defined in `ext/data/`.

### Structure

```
ext/data/<resource>/
  .meta          → field definitions and configuration (JSON)
  <uuid>         → one record per file (JSON, no extension)
```

Example:

```
ext/data/articles/
  ├── .meta
  ├── a1b2c3d4e5f6g7h8
  └── z9y8x7w6v5u4t3s2
```

### .meta file

Defines the structure and behavior of a resource. All fields must be explicitly configured.

```json
{
  "fields": {
    "title": {
      "name": "Title",
      "type": "name",
      "required": true
    },
    "published": {
      "name": "Published",
      "type": "boolean"
    },
    "body": {
      "name": "Body",
      "type": "html",
      "buttons": "h2,h3,h4,bold,italic,orderedlist,unorderedlist,quote,anchor",
      "media": true,
      "media_sizes": "sm-90,md-70,lg-60,xl-50,xxl-40",
      "admin_col": false
    },
    "sort_order": {
      "name": "Sort order",
      "type": "text",
      "admin_col": false
    }
  },
  "sort": {
    "field": "sort_order",
    "flags": "numeric",
    "order": "asc"
  }
}
```

### Field types

| Type | Description |
|---|---|
| `text` | Single-line text |
| `textarea` | Multi-line plain text |
| `html` | Rich text (medium-editor) |
| `slug` | URL-safe slug — auto-computed from source fields, manually overridable |
| `boolean` | True/false toggle |
| `date` | Date picker |
| `email` | Email address |
| `url` | URL |
| `password` | Encrypted password field |
| `image` | Single image upload |
| `file` | Single file upload |
| `upload` | Generic upload |
| `gallery` | Multiple image upload |
| `select` | Dropdown — fixed options or from resource |
| `number` | Numeric input — supports optional `min` and `max` |
| `color` | Color picker |

Field type names must match exactly.

### Field configuration

Common options per field:

| Key | Type | Description |
|---|---|---|
| `name` | string | Display label in admin |
| `required` | boolean | Validation — must be set on all title/name fields |
| `admin_col` | boolean | Show in admin overview table (default: true) |
| `multi` | boolean | Allow multiple values |
| `i18n` | boolean | Translate per language |
| `accept` | string | File type restriction for image/file fields |
| `slug` | boolean | Auto-generates a URL-safe slug from this field's value. Used on `name` fields that serve as routing keys. |

**HTML fields** must explicitly use one of two configurations:

Simple (short text blocks, e.g. intro):
```json
"buttons": "bold,italic",
"admin_col": false
```

Rich (full content, e.g. body):
```json
"buttons": "h2,h3,h4,bold,italic,orderedlist,unorderedlist,quote,anchor",
"media": true,
"media_sizes": "sm-90,md-70,lg-60,xl-50,xxl-40",
"admin_col": false
```

Never define an `html` field without choosing one of these. `media_sizes` is required when `media: true`.

**Slug fields:**

Auto-computed from one or more source fields. The user can override the value manually. Clearing the field re-enables auto-computation.

```json
"url_slug": {
  "name": "URL slug",
  "type": "slug",
  "source": "title",
  "admin_col": false
}
```

Multiple sources are comma-separated — values are joined with a space before slugifying:

```json
"url_slug": {
  "name": "URL slug",
  "type": "slug",
  "source": "title,date",
  "admin_col": false
}
```

With `"source": "title,date"` and title `"Jazz Night"`, date `"2026-04-09"`, the slug becomes `jazz-night-2026-04-09`. Always add `url_slug` to the resource `index` array so it can be looked up in `route.inc`.

**Select fields:**

Fixed options:
```json
"type": "select",
"options": {
  "new": "New",
  "active": "Active",
  "closed": "Closed"
}
```

Options from another resource:
```json
"type": "select",
"resource": "categories"
```

### Root-level .meta configuration

| Key | Description |
|---|---|
| `sort` | Default sort (`field`, `flags`: `string`/`numeric`, `order`: `asc`/`desc`) |
| `validate` | Validation rules (e.g. `natural-short-text`) |
| `languages` | Enabled languages for this resource |
| `ai_prompts` | Per-field AI translation instructions |
| `actions` | Object with a `url` key — adds a "View" button in the admin record list pointing to the frontend URL of the record. Shortcodes are evaluated in the URL value. Example: `{"url": "[#base-url#]/articles/[#record.title_slug#]"}` |
| `events` | Resource lifecycle event declarations. Supported keys: `create`, `update`, `delete`. Values are arrays of named events or `job:<type>` queue entries. See below. |
| `splitdir` | Boolean. When `true`, records are stored in a two-level subdirectory tree by UUID prefix for filesystem performance at scale (> ~10,000 records). See §12 API — Scalability. |
| `index` | Array of field names to index. Creates fast lookup paths for those fields. See §4 Indexes below. |

### Resource lifecycle events

Nimbly 1.1 uses `.meta` event declarations for resource lifecycle side effects. A resource can declare what should happen after a record is created, updated, or deleted:

```json
{
  "events": {
    "create": ["job:application-email-created"],
    "update": ["application-updated"],
    "delete": ["application-deleted"]
  }
}
```

Event payloads are intentionally small:

```php
[
    'action' => 'create',
    'resource' => 'membership-applications',
    'uuid' => 'record-uuid',
    'data' => $record_data, // available for create/update/delete when already loaded
]
```

Plain event names are dispatched to module libraries by convention:

```text
ext/modules/member/lib/application-updated.php
function application_updated($event) {}
```

Entries prefixed with `job:` enqueue a `.jobs` record instead of running work inline:

```json
"events": {
  "create": ["job:application-email-created"]
}
```

Core setup creates the `.jobs` resource. `job_enqueue()` also creates it lazily if an older install does not have it yet.

Run queued jobs from CLI:

```bash
php core/cli/nimbly.php jobs:run
```

By default, `jobs:run` processes one eligible job. Pass an explicit limit only for catch-up runs:

```bash
php core/cli/nimbly.php jobs:run 25
```

Job handlers are module-discovered by convention:

```text
ext/modules/member/lib/application-email-created.php
function application_email_created_job($job) {}
```

The queue stores intent and payload, not shell commands. Handlers may send email, call APIs, or perform other side effects.

### System fields (auto-managed)

Every record automatically has: `uuid`, `_created`, `_modified`, `_created_by`, `_modified_by`. Never define these in `.meta`.

### Hidden resources

Resources whose names begin with `.` are hidden from the admin data management UI by default (Unix hidden-file convention). They remain fully accessible via the data library and API.

Built-in hidden resources: `.config`, `.content`, `.routes`, `.i18n`, `.jobs`, `.state`.

Custom hidden resources follow the same convention — name them with a leading dot to keep them out of the admin overview.

### Indexes

Indexes allow fast lookup of records by a field value without scanning the entire resource. This is the standard way to resolve a slug from a URL to a record UUID.

#### Enabling indexes

Add an `index` array to `.meta` listing the field names to index:

```json
{
  "fields": {
    "title": { "name": "Title", "type": "name", "required": true, "slug": true },
    "title_slug": { "name": "Slug", "type": "text" }
  },
  "index": ["title_slug"]
}
```

Every time a record is written (`data_create` / `data_update`), the index is updated automatically.

#### How indexes are stored

For each indexed field, Nimbly creates an empty file at:

```
ext/data/<resource>/.index/<field_name>/<md5(field_value)>/<record_uuid>
```

For example, an article with `title_slug = "my-article"` and `uuid = "abc123"` would create:

```
ext/data/articles/.index/title_slug/1a79a4d60de6718e8e5b326e338ae533/abc123
```

The file is empty — its presence is the index entry. Storing under `.index/` keeps indexes separate from record files and ensures they are naturally skipped by directory scans (which skip dot-prefixed entries).

#### With splitdir

When `splitdir: true` is also set, index directories follow the same two-level split:

```
ext/data/<resource>/.index/<field_name>/<aa>/<bb>/<md5(value)>/<record_uuid>
```

The data library handles this transparently — same API regardless of whether `splitdir` is set.

#### Looking up a record by slug (route.inc pattern)

```php
<?php

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) return;

$slug = $parts[0];
load_library('data');
load_library('md5');

$records = data_read_index('articles', 'title_slug', md5_uuid($slug));
if (empty($records)) return;

$record = reset($records);
set_variable_dot('record', $record);
router_accept();
```

`data_read_index($resource, $index_name, $index_uuid)` returns an associative array of `uuid => record` for all records matching the indexed value. For slug lookups this is always one record.

#### Rebuilding indexes (reindex CLI)

If you add `index` to an existing resource's `.meta`, existing records won't have index entries yet. Rebuild them with:

```bash
php core/cli/nimbly.php reindex
# prompts — lists all indexed resources and asks you to choose

php core/cli/nimbly.php reindex articles
# direct — reindexes the 'articles' resource immediately
```

The reindex command is idempotent — safe to run multiple times.

### Data caching

The data library automatically caches all query results in `ext/data/.tmp/cache/`. The cache is invalidated when any record in the queried resource is modified. No manual cache management is needed — it is fully automatic.

---

### Resource conventions (required)

These rules are mandatory for all resources unless explicitly stated otherwise.

**Always include:**
- `"required": true` on all name/title fields
- `published` boolean field on content resources
- `sort_order` field + `sort` config on list resources (partners, logos, cards, team members, etc.)
- `"admin_col": false` on images, html, urls, large text, and technical fields like sort_order

**Always consider:**
- Does this resource need a `published` field?
- Does ordering matter? Add `sort_order` + `sort`
- Is this linked from a URL? Add a slug field and list it in `index`
- Does any field need to be unique? Add it to `unique` and usually to `index`
- Does the admin overview need a specific column order? Use `admin_columns`, including `_modified` and `_created` when useful
- Does content need to be translated? Add `languages` and `i18n` per field
- Can this be smaller while still fully solving the current requirement?
- Can existing system metadata or a single field index solve this without extra custom fields?

**Admin visibility — fields to hide by default:**
- images
- html content
- urls
- large text fields
- sort_order and other technical fields

### Production-ready .meta examples

#### Example A: Partners (list with sorting)

```json
{
  "fields": {
    "title": {
      "name": "Partner name",
      "type": "text",
      "required": true
    },
    "published": {
      "name": "Published",
      "type": "boolean"
    },
    "logo": {
      "name": "Logo",
      "type": "image",
      "admin_col": false
    },
    "website": {
      "name": "Website",
      "type": "url",
      "admin_col": false
    },
    "sort_order": {
      "name": "Sort order",
      "type": "text",
      "admin_col": false
    }
  },
  "sort": {
    "field": "sort_order",
    "flags": "numeric",
    "order": "asc"
  }
}
```

#### Example B: Articles (content with date sorting)

```json
{
  "fields": {
    "title": {
      "name": "Title",
      "type": "text",
      "required": true
    },
    "published": {
      "name": "Published",
      "type": "boolean"
    },
    "featured": {
      "name": "Featured",
      "type": "boolean"
    },
    "date": {
      "name": "Date",
      "type": "date"
    },
    "main_img": {
      "name": "Image",
      "type": "image",
      "admin_col": false
    },
    "intro": {
      "name": "Intro",
      "type": "html",
      "buttons": "bold,italic",
      "admin_col": false
    },
    "main_text": {
      "name": "Main text",
      "type": "html",
      "buttons": "h2,h3,h4,bold,italic,orderedlist,unorderedlist,quote,anchor",
      "media": true,
      "media_sizes": "sm-90,md-70,lg-60,xl-50,xxl-40",
      "admin_col": false
    }
  },
  "sort": {
    "field": "date",
    "flags": "string",
    "order": "desc"
  }
}
```

#### Example C: Admin overview columns

Use `admin_columns` to control the admin table column order. This can include built-in system columns `_modified` and `_created`.

```json
{
  "admin_columns": ["email", "_modified", "_created"],
  "sort": {
    "field": "_modified",
    "flags": "numeric",
    "order": "desc"
  }
}
```

### Validation checklist before outputting a resource schema

- [ ] All name/title fields have `"required": true`
- [ ] HTML fields have `buttons` configured (simple or rich)
- [ ] HTML fields with `media: true` have `media_sizes`
- [ ] `admin_col: false` is set on images, html, urls, large text, sort_order
- [ ] List resources have `sort_order` field and `sort` config
- [ ] Content resources have a `published` boolean
- [ ] No system fields defined (`uuid`, `_created`, etc.)

---

## 5. Multi-language (i18n)

Nimbly supports multi-language systems out of the box. Languages are handled at three levels: site configuration, routing, and data.

### Step 1 — Define site languages

`ext/data/.config/site`:
```json
{
  "languages": ["en", "nl"]
}
```

Rules:
- Required for all multi-language setups
- Only 2-letter language codes
- First language is the fallback

### Step 2 — Set up language routing

Each language gets its own URL prefix: `/en/`, `/nl/`

Redirect root to the detected language in `ext/uri/index.tpl`:
```
[#redirect [#detect-language#]#]
```

Language detection order:
1. URL prefix (`/en/`, `/nl/`)
2. User preference (`?lang=en`)
3. Domain TLD (`.en`, `.nl`)
4. Browser language header
5. Fallback — first language in site config

Once a user is inside a language scope, it persists automatically across navigation.

### Step 3 — Configure resources for translation

Add `languages` to every resource `.meta` that has translated content:

```json
"languages": ["en", "nl"]
```

Mark translatable fields with `i18n: true`:

```json
"title": {
  "type": "text",
  "required": true,
  "i18n": true
},
"intro": {
  "type": "html",
  "buttons": "bold,italic",
  "admin_col": false,
  "i18n": true
}
```

Fields without `i18n: true` are shared across all languages (images, booleans, sort_order, etc.).

### Step 4 — Add static text translations

Use `[#text Key#]` for UI labels in templates. Keys use underscores for spaces.

Create translation files per language:

`ext/data/.i18n/text.en.po`:
```po
msgid "Search"
msgstr "Search"

msgid "Read_more"
msgstr "Read more"
```

`ext/data/.i18n/text.nl.po`:
```po
msgid "Search"
msgstr "Zoeken"

msgid "Read_more"
msgstr "Lees meer"
```

Build the merged base file after changes:
```bash
npm run build:text
```

### Step 5 — Templates per language

Languages are not just translations of the same site — each language can have its own templates, routing, and structure. They are effectively separate sites under `/en/`, `/nl/`, etc.

```
ext/uri/en/index.tpl      → /en/
ext/uri/en/about/index.tpl → /en/about/
ext/uri/nl/index.tpl      → /nl/
ext/uri/nl/about/index.tpl → /nl/about/
```

When structure is shared across languages, extract it into a reusable template:

`ext/uri/nl/about/main.tpl`:
```
[#about-main#]
```

`ext/tpl/about-main/index.tpl`:
```html
<h1>[#text About#]</h1>
<p>[#text About_intro#]</p>
```

### AI-assisted translation

Fields can define translation instructions using `ai_prompts`. The admin uses these to guide AI translation.

Structure:
```json
"ai_prompts": {
  "_all": [...],   → shared context for all languages
  "en": [...],     → English-specific instructions
  "nl": [...]      → Dutch-specific instructions
}
```

**For text fields:**
```json
"title": {
  "type": "text",
  "i18n": true,
  "ai_prompts": {
    "_all": ["Your style is accurate and professional."],
    "en": ["You translate to English."],
    "nl": ["You translate to Dutch."]
  }
}
```

**For HTML fields — strict rules apply:**

HTML fields require explicit instructions to preserve structure. Always include all of the following in `_all`:

```json
"main_text": {
  "type": "html",
  "i18n": true,
  "ai_prompts": {
    "_all": [
      "The input and output are in HTML, properly escaped for safe use in an HTML editor.",
      "You preserve existing HTML structures including images, lists, links and other tags as much as possible.",
      "You do not introduce raw < or > characters in your output, but use &lt; and &gt;.",
      "Your style is accurate and professional."
    ],
    "en": ["You translate to English."],
    "nl": ["You translate to Dutch."]
  }
}
```

Rules for HTML translation:
- Always include the HTML safety instructions in `_all`
- Always preserve images, links, lists, and formatting tags
- Never allow the model to simplify or restructure the HTML

### i18n rules for AI agents

- Always define `languages` in both site config and resource `.meta`
- Always use 2-letter language codes, consistently everywhere
- Only add `"i18n": true` to content fields — not images, booleans, sort_order, dates
- Never translate slug fields or UUIDs
- Keep resource structure identical across languages
- Use `[#text#]` for UI labels; use `i18n` fields for structured content
- Always include HTML safety instructions in `ai_prompts._all` for html fields

---

## 6. Routing

Routes are `.tpl` files in `ext/uri/`. The folder structure maps directly to the URL.

Dynamic URL segments use parentheses:

```
ext/uri/blog/(slug)/index.tpl     → /blog/<anything>/
ext/uri/user/(id)/index.tpl       → /user/<anything>/
```

### Route-scoped JavaScript

If a route folder contains an `index.js` file, it is automatically loaded by the HTML template for that route only. Use this for JavaScript that is specific to a single page and should not be part of the global bundle.

```
ext/uri/dashboard/index.tpl
ext/uri/dashboard/index.js     ← auto-loaded on /dashboard/ only
```

---

## 7. CLI

Nimbly ships a CLI at `core/cli/nimbly.php`. The `nimbly` npm script is the preferred way to invoke it:

```bash
npm run nimbly -- setup
npm run nimbly -- user:create
npm run nimbly -- module:install <name>
```

Equivalent direct invocations:

```bash
php core/cli/nimbly.php setup
php core/cli/nimbly.php user:create
php core/cli/nimbly.php module:install <name>
php core/cli/nimbly.php index:rebuild [resource]
php core/cli/nimbly.php help
```

### Commands

#### `setup`
First-time site initialisation. Safe to re-run — existing files and records are never overwritten.

What it does:
- Creates `.env` with `APP_ENV`, a generated `PEPPER`, and `BASE_PATH` only for subdirectory installs
- Generates `.htaccess` from template
- Creates the `ext/` directory scaffold (`data/`, `static/`, `lib/`, `modules/`, `tpl/`, `uri/`, temp dirs)
- Creates `.config/site`, the `.content` resource, core `.routes` records, and default roles (`admin`, `editor`)
- Creates the `users` resource and an initial admin user

Prompts: **Site name**, **Admin email**, **Admin password**. Steps that are already complete are skipped silently.

**Non-interactive (CI/CD):** Set environment variables to skip all prompts:

| Variable | Description |
|---|---|
| `APP_ENV` | Environment label (`prod`, `stage`, `dev`; default `dev`) |
| `BASE_PATH` | Optional URL base path for subdirectory installs, e.g. `/mysite/`; omit for root installs |
| `PEPPER` | Encryption pepper — set to reuse an existing value; omit to auto-generate |
| `EXT_REPO` | Git remote URL for the ext repo (written to `ext/readme.md`) |
| `SITE_NAME` | Site name written to `.config/site` |
| `ADMIN_EMAIL` | Initial admin user email |
| `ADMIN_PASSWORD` | Initial admin user password (min 8 chars) |

```bash
SITE_NAME="My Site" ADMIN_EMAIL=admin@example.com ADMIN_PASSWORD=secret123 npm run nimbly -- setup
```

#### `create-user`
Creates an additional user account. Prompts for email, role, and password interactively. Available roles are read from `ext/data/roles/`. The user is always also assigned the `user` role. Requires setup to have been run first.

#### `install-module`
Runs a module's `.install.inc` script. Looks in `ext/modules/` first, then `core/modules/` as fallback. Requires setup to have been run first.

```bash
npm run nimbly -- module:install event
```

#### `reindex`
Rebuilds index entries for an indexed resource. Use this after adding `index` to an existing resource's `.meta`, or after importing records outside of the normal API flow.

```bash
php core/cli/nimbly.php index:rebuild             # interactive: lists indexed resources, prompts for choice
php core/cli/nimbly.php index:rebuild articles    # direct: reindex the 'articles' resource
```

The command scans all records in the resource and creates any missing index files. It is idempotent — existing entries are left untouched.

#### `schedule:run`
Runs due scheduled commands. The default cron is:

```bash
* * * * * php /path/to/site/core/cli/nimbly.php schedule:run
```

Projects may define schedules in `ext/cli/`. The scheduler selects files in this order:

1. `SCHEDULE_FILE` from `.env` or the process environment, as an explicit file path.
2. `ext/cli/schedule.<env>.inc`, where `<env>` comes from `SCHEDULE_ENV`, `APP_ENV`, or `NIMBLY_ENV`.
3. `ext/cli/schedule.inc`.
4. `core/cli/schedule.inc`.

Environment aliases are normalized: `production` → `prod`, `staging` → `stage`, `development` and `local` → `dev`.

Use environment-specific schedule files when staging or development must run background jobs but must not run production-only tasks such as member reminders.

Scheduler last-run state is stored in `ext/data/.state/schedule`. Existing installs with older `ext/data/.config/schedule` state are read as a migration fallback until the scheduler writes the new state file.

---

## 8. Build

```bash
npm run build       # full build: Tailwind + CSS + JS + i18n
npm run build:tw    # build Tailwind once
npm run build:css   # build CSS (esbuild)
npm run build:js    # build JS (esbuild)
npm run build:text  # merge .po translation files
npm run up          # start Docker dev environment
```

Built files go to `ext/static/`. Always run build after changing CSS, JS, or Tailwind classes.

---

## 9. Forms

The forms module handles front-end form submissions with CSRF protection, honeypot spam filtering, validation, and Alpine.js-powered submission via the REST API.

### Rendering a form — `[#build-form name#]`

`[#build-form contact#]` reads `contact.json` from the same directory as the current route and renders a complete form. The form submits via Alpine.js to the API — no page reload.

Form definition file (`ext/uri/contact/contact.json`):

```json
{
  "name": "contact",
  "resource": "leads",
  "success_message": "Thank you, we will be in touch.",
  "fields": {
    "name": {
      "type": "text",
      "name": "Your name",
      "required": true
    },
    "email": {
      "type": "email",
      "name": "Email address",
      "required": true
    },
    "message": {
      "type": "textarea",
      "name": "Message"
    }
  },
  "buttons": [
    { "type": "submit", "title": "Send" }
  ]
}
```

The `resource` field is the target resource that will receive the new record via the API. Make sure that resource exists in `ext/data/` with a matching `.meta`.

For a public form, explicitly allow the API POST with a route such as:

```
ext/uri/api/v1/leads/index.tpl
```

```html
[#api-allow post leads#]
```

This allows anonymous submissions for that one method/resource pair while keeping the rest of the API protected.

### Field types in forms

Same type names as resource fields: `text`, `textarea`, `email`, `url`, `select`, `upload`. Add `"help": "Hint text"` to any field for a help label below the input.

**Select field:**
```json
"status": {
  "type": "select",
  "name": "Type",
  "options": {
    "general": "General question",
    "support": "Support request"
  }
}
```

**Upload field:**
```json
"attachment": {
  "type": "upload",
  "name": "Attachment",
  "accept": ".pdf,.doc"
}
```

For forms with file upload, also set `"upload_field": "attachment"` at the root of the form definition.

### Grouping fields

Use `group_start` / `group_end` to visually group fields side by side:

```json
"fields": {
  "_g1": { "type": "group_start", "name": "Name" },
  "first_name": { "type": "text", "name": "First name" },
  "last_name":  { "type": "text", "name": "Last name" },
  "_g1e": { "type": "group_end" }
}
```

### CSRF and honeypot

Both are handled automatically by `[#build-form#]`. The form includes a hidden `form_key` (session-matched token) and a honeypot field. No extra configuration needed.

### Custom form handlers

For forms that need server-side processing beyond writing to a resource (e.g. file imports or enqueueing a background job), add handler files alongside the route:

```
ext/uri/contact/contact.json       ← form definition
ext/uri/contact/post_contact.inc   ← runs on submission
ext/uri/contact/validate_contact.inc  ← runs before post, return false to abort
```

The files are named `post_{name}.inc` and `validate_{name}.inc`, where `{name}` matches the form's `name` field. Both are optional — use one or both as needed.

---

## 10. Rich content fields — end-to-end

This section shows the full flow for an editable HTML field: resource definition → template output → inline admin editing.

### Step 1 — Define the field in `.meta`

```json
"intro": {
  "name": "Intro",
  "type": "html",
  "buttons": "bold,italic",
  "admin_col": false,
  "i18n": true
},
"body": {
  "name": "Body",
  "type": "html",
  "buttons": "h2,h3,h4,bold,italic,orderedlist,unorderedlist,quote,anchor",
  "media": true,
  "media_sizes": "sm-90,md-70,lg-60,xl-50,xxl-40",
  "admin_col": false,
  "i18n": true
}
```

- `buttons` is mandatory — always specify the exact toolbar buttons needed
- `media: true` enables the media browser; requires `media_sizes`
- `admin_col: false` is mandatory for html fields (too large for table view)
- Add `i18n: true` if the site is multi-language

### Step 2 — Load the record in route.inc

```php
<?php
router_deny();
$parts = router_match(__FILE__);
if ($parts === false) return;
load_library('data');
$record = data_read('articles', $parts[0]);
if (!$record) return;
set_variable('article', $record);
router_accept();
```

### Step 3 — Output in the template

Use `[#get-html#]` to output an editable html field. When a logged-in admin views the page, the field becomes inline-editable directly in the frontend.

```html
<div class="prose">
  [#get-html article.intro#]
</div>

<div class="prose prose-lg">
  [#get-html article.body#]
</div>
```

For multi-language content, wrap with `[#get-i18n#]` to get the active-language value before passing to `get-html`:

```
[#get-html [#get-i18n article.body#]#]
```

### Step 4 — Enable inline editing

Add `[#html#]` to the route and ensure the user module is active (auto-loaded). Logged-in admins will then see the inline editor on `[#get-html#]` fields.

### Inline editing attributes

For finer control over the inline editor (e.g. different toolbar than the `.meta` default, or to enable media on a field that doesn't have it in `.meta`), add `data-nb-edit` directly to a wrapper element:

```html
<div data-nb-edit="event.[#record.uuid#].main_text" data-nb-edit-options='{
    "buttons":"h2,h3,bold,italic,anchor",
    "media": true,
    "media_sizes":"md-70,lg-60"}'>
  [#get-html record.main_text#]
</div>
```

For inline image replacement, use `data-nb-edit-img` on the image wrapper:

```html
<div data-nb-edit-img="event.[#record.uuid#].main_img">
  [#get-img-html [#record.main_img#] sizes="xs-100,md-50"#]
</div>
```

Both attributes only activate for logged-in admins. The value format is `resource.uuid.field`.

---

## 11. Admin

The built-in admin is available at `/nb-admin/`.

The admin (1.1+) uses DaisyUI components. The following shortcodes from `core/modules/admin/lib/` handle data loading for admin views:

- `[#get-resource-records resource=users role=table#]` — loads all records + table-filtered fields into `data.records` and `data.fields`
- `[#get-resource-record resource=users uuid=abc123#]` — loads a single record for editing, with i18n and encryption handling
- `[#get-resource-meta resource=users#]` — loads only field definitions into `data.fields`

The legacy `_dep_` admin UI has been removed. Active admin routes and templates live in the non-`_dep_` admin paths.

---

## 12. API

The Nimbly API has routes for every resource. Access is still controlled by role permissions, bearer tokens, or an explicit public `[#api-allow#]` route for narrow cases such as public forms.

### Endpoints

```
GET    /api/v1/{resource}          → list all records
POST   /api/v1/{resource}          → create a new record
GET    /api/v1/{resource}/{uuid}   → get a single record
PUT    /api/v1/{resource}/{uuid}   → update a record (partial update — only supplied fields are changed)
DELETE /api/v1/{resource}/{uuid}   → delete a record
DELETE /api/v1/{resource}          → delete all records
```

### Authentication

Obtain a Bearer token by posting credentials:

```bash
curl -X POST "/api/v1/auth/token" \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "yourpassword"}'
```

Response:
```json
{
  "token": "anSojGGKOveDuIypVGbx...",
  "token_created": 1741265734,
  "token_expires": 1741308617,
  "code": 200,
  "success": true,
  "status": "ok"
}
```

Tokens expire after **10 minutes**. Refresh before expiry with a GET to the same endpoint:

```bash
curl -X GET "/api/v1/auth/token" \
  -H "Authorization: Bearer YOUR_VALID_TOKEN"
```

Include the token in every API request:

```
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json
```

### Response format

All successful responses return `success: true` and `status: ok`:

```json
{
  "articles": {
    "abc123": {
      "uuid": "abc123",
      "title": "Hello world",
      "published": "1"
    }
  },
  "count": 1,
  "message": "RESOURCE_CREATED",
  "code": 201,
  "success": true,
  "status": "ok",
  "memory_usage": "403Kb",
  "execution_time": "0.010s"
}
```

| Field | Description |
|---|---|
| `count` | Number of records returned or affected |
| `message` | `RESOURCE_CREATED` (201), `RESOURCE_UPDATED` (200), `RESOURCE_DELETED` (200) |
| `code` | HTTP status code |
| `success` | `true` on success, `false` on error |
| `execution_time` | Server processing time |

### Error responses

```json
{
  "message": "ACCESS_DENIED",
  "needs": "api_delete_users,api_delete_(any),api_(any)",
  "code": 403,
  "success": false,
  "status": "error"
}
```

Common error codes:

| Code | Message | Description |
|---|---|---|
| 400 | `INVALID_DATA` | Missing required fields or invalid data |
| 401 | `UNAUTHORIZED` | Missing or invalid token |
| 403 | `ACCESS_DENIED` | Role lacks the required permission |
| 404 | `RESOURCE_NOT_FOUND` | Record or resource does not exist |
| 409 | `CONFLICT` | Record already exists |
| 500 | `INTERNAL_ERROR` | Server-side failure |

### Authorization

Access is role-based. When a request is denied, the `needs` field lists the permission patterns that would grant access:

```json
"needs": "api_delete_users,api_delete_(any),api_(any)"
```

Patterns follow the format `api_{method}_{resource}`. Roles and their permissions are managed in the admin under `/nb-admin/roles/`.

### Custom UUID

Supply a `uuid` field in the POST body to use a specific identifier instead of an auto-generated one:

```bash
curl -X POST "/api/v1/profiles" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"uuid": "my-id", "name": "Test User"}'
```

Useful when the caller already has a meaningful identifier (e.g. an external system ID) to use as the record key.

### File upload

Files are uploaded to the special `.files` resource as `multipart/form-data`:

```bash
curl -X POST "/api/v1/.files" \
  -H "Authorization: Bearer TOKEN" \
  -F "file=@photo.jpg"
```

Response includes the file UUID, which is the **MD5 checksum** of the file content:

```json
{
  "files": {
    "uuid": "44556fd2a0d9463b6506e6e101e20bfe",
    "name": "photo.jpg",
    "type": "image/jpeg",
    "size": 2048
  },
  "message": "RESOURCE_CREATED",
  "code": 201
}
```

Re-uploading an identical file returns `RESOURCE_CREATED` with the same UUID — the upload is idempotent by checksum.

### File metadata — `.files_meta`

Every uploaded file has a corresponding metadata record in `.files_meta/{uuid}`. This is a separate resource from `.files` (which stores the raw binary). The metadata record contains:

| Field | Description |
|---|---|
| `uuid` | File UUID — MD5 checksum of the file content |
| `name` | Original filename (e.g. `KH-Menukaart.pdf`) |
| `title` | Admin-set display title (e.g. `KetelHuis Menukaart`) — may be empty |
| `type` | MIME type (e.g. `application/pdf`, `image/jpeg`) |
| `size` | File size in bytes |

When displaying a file reference stored as a UUID (e.g. in page settings or a resource field), always read the display name and type from `.files_meta`, not from the UUID itself. In templates, `nb.media_library.unfiltered` contains the full `.files_meta` records loaded at page init — use this for client-side display without extra API calls.

Prefer `title` over `name` as the user-facing display label; fall back to `name` when `title` is empty.

### Image serving

Uploaded images are served at `/img/{uuid}/{spec}`:

| Spec | Example | Description |
|---|---|---|
| `{W}x{H}f` | `300x300f` | Fit into box, aspect ratio preserved |
| `{W}x{H}c` | `300x200c` | Crop from center to exact dimensions |
| `{W}w` | `300w` | Resize to width |
| `{H}h` | `300h` | Resize to height |

```
/img/44556fd2a0d9463b6506e6e101e20bfe/500x500c
```

Images are served as **WebP**. Upscaling is not allowed.

### Scalability

Nimbly loads resources into memory for fast access. By default, all records live in one flat directory. Above ~10,000 records filesystem performance starts to degrade.

For high-volume resources, enable `splitdir` in `.meta`:

```json
{
  "fields": { ... },
  "splitdir": true
}
```

With `splitdir` enabled, records are stored in a two-level directory tree based on the first characters of their UUID (e.g. `ab/cd/<uuid>`). This keeps directory sizes small and maintains performance at large scale. The data library handles the path transparently — no changes needed in templates or code.

---

## 13. Custom Shortcode Libraries

Custom shortcode libraries live as single PHP files in `ext/lib/`:

```
ext/lib/prepare-events.php
```

The older directory format is still supported for compatibility:

```
ext/lib/prepare-events/prepare-events.php
```

Use the directory format only when the library needs support files that belong beside the entrypoint.

To migrate existing single-file library directories automatically:

```bash
php core/cli/nimbly.php migrate-lib-flat
```

The function must be named `<name>_sc($params)` with hyphens converted to underscores:

```php
<?php

function prepare_events_sc($params)
{
    // Read a variable set by [#data#] or [#set#]
    $events = get_variable('data.event');

    $result = [];
    $today = date('Y-m-d');
    foreach ($events as $event) {
        if ($event['date'] >= $today) {
            $result[] = $event;
        }
    }

    // Write back — [#repeat data.event#] will now only see future events
    set_variable('data.event', $result);
}
```

Called in templates exactly like any other shortcode:

```
[#data event filter=published:yes sort=date|string|asc#]
[#prepare-events#]
[#repeat data.event#]
```

### PHP variable API

| Function | Description |
|---|---|
| `get_variable('data.event')` | Reads any template variable by dot-notation path |
| `set_variable('data.event', $value)` | Writes a variable at the given path |
| `set_variable_dot('record', $array)` | Writes an associative array as a dot-notation variable (`record.field`, `record.uuid`, etc.) |

### Libraries are for business logic, not simple value lookup

Use a library only when the work involves real business logic or data preparation that cannot be expressed cleanly with existing shortcodes. Simple lookup, conditionals, date formatting, and direct field output belong in templates using `[#get#]`, `[#if#]`, `[#fmt#]`, `[#date#]`, `[#data#]`, and similar shortcodes.

Do not create a library just to copy fields into new variables, rename values for display, or wrap a template call. That adds indirection without behavior.

Keep responsibilities narrow. A library named for one concept must not quietly take ownership of unrelated concerns such as rendering decisions, payment links, session banners, analytics, or layout state. Split those concerns into templates, existing shortcodes, configuration data, or separate purpose-built libraries.

When a library is justified, it should prepare data and set template variables. HTML belongs exclusively in `.tpl` files — never in PHP strings returned from a library function.

Prefer returning a small value when the logic is just classification:

```php
// correct
function membership_status_sc($_params)
{
    $expires = get_variable('user.membership_expires');
    if ($expires === '2037-12-31') {
        return 'lifetime';
    }
    if ($expires <= date('Y-m-d')) {
        return 'expired';
    }
    return 'active';
}
```

```html
[#member-status-[#membership-status#]#]
```

Use prepare-then-render when logic needs to compute multiple values for a template:

```php
function prepare_membership_status_sc($_params)
{
    load_library('env');
    $expires = get_variable('user.membership_expires');
    $link_annual = env('STRIPE_LINK_ANNUAL');
    set_variable('member-expires-date', date('M j, Y', strtotime($expires)));
    set_variable('member-link-annual', htmlspecialchars($link_annual));
}
```

```html
[#prepare-membership-status#]
[#membership-status#]
```

```php
// wrong — never do this
function membership_status_switch_sc($_params)
{
    return '<section class="mb-10"><div class="bg-cnormal ...">Active Member</div></section>';
}
```

If a library must render a template, use `run_buffered($path_to_tpl_file)`. The path is a filesystem path; anchor it with `dirname(__FILE__)`. Templates for a module live in `ext/modules/<name>/tpl/`.

---

## 14. Modules

A module is a self-contained feature that bundles its own routes, templates, libraries, and install logic.

Use a module when:
- A feature has **both a library (shortcode) and a dedicated route** that belong together — e.g. a Stripe integration with a webhook endpoint and a `stripe-webhook` shortcode.
- A feature ships as a **reusable unit** — e.g. an event system, a shop, a blog — rather than a loose set of pages.
- A feature needs an **install script** to create resources or register routes.

Use `ext/lib/` and `ext/uri/` directly (without a module) for things that don't naturally group together — a standalone utility shortcode, or a one-off page with no associated library.

### Module directory structure

```
ext/modules/<name>/
  .install.inc     # Install script — runs once when the module is installed
  lib/             # Shortcode libraries scoped to this module
  tpl/             # Templates scoped to this module
  uri/             # Routes scoped to this module (merged into the URL space)
```

Module templates follow the same `index.tpl` convention as `ext/tpl/`. A template at `ext/modules/member/tpl/member-status/index.tpl` is callable as `[#member-status#]`. Flat `.tpl` files inside the folder are partials — only `index.tpl` is the public shortcode entry point.

Routes inside `modules/<name>/uri/` are served at the same paths as if they were in `ext/uri/`. A route at `ext/modules/event/uri/event/(slug)/index.tpl` is accessible at `/event/<slug>/`.

### Module auto-discovery

All modules in `ext/modules/` and `core/modules/` are discovered automatically on the first template lookup of any request. There is no need to declare or load a module explicitly — its templates, libraries, and routes are always available.

The `[#module name#]` shortcode is a no-op and should not be used. Use `[#nop module event#]` or a comment if you want to document a dependency inline.

### `.install.inc`

The install script runs when `php nimbly.php install-module <name>` (or the admin install button) is executed. Use it to create the resource(s) and register any dynamic routes the module needs.

```php
<?php

load_library("data");

// Create the resource if it doesn't exist yet
$result = data_create_resource("events", [
    "fields" => [
        "title"     => ["name" => "Title", "type" => "name", "required" => true, "slug" => true],
        "published" => ["name" => "Published", "type" => "boolean"],
        "date"      => ["name" => "Date", "type" => "date"],
        "body"      => ["name" => "Body", "type" => "html",
                        "buttons" => "h2,h3,bold,italic,anchor",
                        "media" => true, "media_sizes" => "sm-90,md-70,lg-60",
                        "admin_col" => false],
        "sort_order" => ["name" => "Sort order", "type" => "text", "admin_col" => false]
    ],
    "sort" => ["field" => "sort_order", "flags" => "numeric", "order" => "asc"]
]);

// Register any dynamic routes this module needs
$route = 'events/(slug)';
$result &= data_exists(".routes", md5($route))
    || data_create(".routes", md5($route), ["route" => $route, "order" => 200]);

return $result;
```

### PHP data API (available in `.install.inc` and `route.inc`)

| Function | Description |
|---|---|
| `data_create_resource($name, $meta)` | Creates a new resource with the given `.meta` definition |
| `data_create($resource, $uuid, $data)` | Creates a new record |
| `data_read($resource, $uuid)` | Reads a single record by UUID |
| `data_exists($resource, $uuid)` | Returns true if a record exists |
| `load_library($name)` | Loads a shortcode library so its PHP functions are available |

### Slug-to-UUID routing

Use the index system to resolve a slug from the URL to a record. Add `title_slug` to the `index` array in `.meta`, then look it up in `route.inc`:

```php
<?php

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) return;

$slug = $parts[0];
load_library('data');
load_library('md5');

$records = data_read_index('events', 'title_slug', md5_uuid($slug));
if (empty($records)) return;

$record = reset($records);
set_variable_dot('record', $record);

router_accept();
```

The record UUID is stable and random — slugs are stored as regular fields and indexed for fast lookup. If the slug field changes, the index updates automatically on next save.

---

## 15. UX principles

Before deciding what to put on a page, reason through these questions:

- **Does the user know what this is?** Only show data the user can interpret and act on. Internal IDs, legacy reference numbers, and system fields have no place on a user-facing page — keep them in the data layer.
- **Does it help the user right now?** Every element must earn its place. If it does not reduce confusion, enable an action, or communicate something the user needs, leave it out.
- **Does it match the user's mental model?** (Nielsen heuristic #2 — match between system and real world.) Show concepts in the user's language, not the system's. A membership number from an old platform is meaningless to someone who never knew it existed.
- **Is the hierarchy clear?** The most important information should be most prominent. Supporting details are secondary. Technical details are hidden or absent.
- **Is there friction?** Every extra field, label, or link adds cognitive load. Default to less. Add only when there is a clear user need.

Apply this reasoning before adding any field, section, or link to a template.

---

## 16. Anti-patterns

- Do not modify `core/`
- Do not add database concepts (tables, joins, foreign keys) — use resources
- Do not define system fields (`uuid`, `_created`, etc.) in `.meta`
- Do not create HTML fields without explicit `buttons` config
- Do not create resources without considering `admin_col`, sorting, and required fields
- Do not add `i18n: true` to non-content fields (images, booleans, sort_order, dates)
- Do not define `languages` on a resource without also defining it in site config
- Do not create sloppy or inconsistent resource schemas. Keep them production-ready, but still minimal and requirement-driven.
- **Do not put HTML in library PHP files** — libraries set variables and call `run_buffered()`, templates render HTML

---

## 17. Pending changes

The following areas are under active development and will be updated here as they are finalized:

- **Resource display names** — the `resource-name` shortcode currently derives singular/plural from the slug (strips trailing `s`, handles `ies→y`). Plan: allow `.meta` to define `name_singular` and `name_plural` with optional i18n:
  ```json
  "name_singular": { "en": "Client", "nl": "Klant" },
  "name_plural":   { "en": "Clients", "nl": "Klanten" }
  ```
  `resource-name` would use these when present and fall back to slug-based logic otherwise.
- **Frontend DaisyUI component patterns** — the admin uses DaisyUI 5. Frontend DaisyUI component patterns will be documented as they become standardized.

---

## 18. Upgrading from core 1.0 to core 1.1

### What changed

| Area | 1.0 | 1.1 |
|---|---|---|
| UUID | Could be derived from a field value via `md5_uuid(pk_value)` | Always a stable random identifier — never derived |
| `.meta` `pk` key | Defined which field drove the UUID | Removed entirely |
| Slug routing | Routes did `data_exists($resource, md5_uuid($slug))` | Routes use `data_read_index($resource, 'slug_field', md5_uuid($slug))` |
| Index storage | `.index/` subdir (also 1.0 late) | Same, fully automatic |
| `data_update_pk()` | Existed — renamed data files on pk change | Removed |
| Resource side effects | Automatic global `data-create` trigger handlers such as `member-on-data-create` | Explicit resource `.meta` `events`, optionally using `job:<type>` |
| Email delivery | Configured in `.services` resource (SMTP credentials stored encrypted) | Configured via `.env`: `MAIL_SERVICE`, `MAIL_FROM`, `MAIL_FROM_NAME`, provider key (e.g. `RESEND_API_KEY`) |
| Password reset email | Sent synchronously over SMTP during the web request | Enqueued as a `password-reset` job; processed by the job runner |

**Core rule in 1.1:** the UUID is the primary key and it never changes. Slugs are stored as normal fields and looked up via indexes.

### Migration steps

#### 1. Update core

Pull the latest core via the admin (**Settings → Update Core**) or:

```bash
git pull   # run from the project root (core repo)
```

#### 2. Run the migration command

The `upgrade-11` CLI command is the normal operator-facing entrypoint for the Nimbly 1.1 upgrade:

```bash
php core/cli/nimbly.php upgrade-11
```

The upgrade command also updates the Tailwind CSS entrypoint at `css/tw/in.css`
from the Tailwind 3 `@tailwind base/components/utilities` directives to the
Tailwind 4 format:

```css
@config "../../tailwind.config.js";
@import "tailwindcss";
```

This matters because Tailwind 4 reads the project config from the CSS
entrypoint. The command preserves any custom CSS below those directives.

It also removes legacy Tailwind Elements bundles from `ext/static/`
(`tw-elements*`). Core 1.1 uses Alpine.js and DaisyUI for admin interactivity,
so these assets should not remain in upgraded projects.

Internally, the resource `pk` migration step is handled by:

```bash
php core/cli/nimbly.php migrate-pk-index
```

For each resource whose `.meta` still has a `pk` key it will:

1. Add the pk field to the `index` array in `.meta` (if not already there)
2. Create index entries for all records — including the **self-referential** entries (`index_uuid === record_uuid`) that exist because 1.0 records had `uuid = md5_uuid(pk_field_value)`. The standard `reindex` command skips these; `migrate-pk-index` creates them explicitly so that `data_read_index` can find them.
3. Remove `pk` from `.meta` and save the file

It also reports legacy `*-on-data-create` trigger handlers so they can be migrated manually to `.meta` events.

The command is interactive and asks for confirmation before making any changes.

#### 3. Update route.inc files

Any route that used the old `data_exists` + `md5_uuid` lookup must be updated to use `data_read_index`.

**Old pattern (1.0):**

```php
$slug = $parts[0];
load_library('data');
load_library('md5');

if (!data_exists('articles', md5_uuid($slug))) return;
set_variable('slug', $slug);

router_accept();
```

**New pattern (1.1):**

```php
$slug = $parts[0];
load_library('data');
load_library('md5');

$records = data_read_index('articles', 'url_slug', md5_uuid($slug));
if (empty($records)) return;

$record = reset($records);
set_variable_dot('record', $record);

router_accept();
```

The slug field name (`url_slug` in the example) must match what is defined in `.meta` and listed in its `index` array.

#### 4. Migrate legacy trigger handlers to `.meta` events

Core 1.0 supported automatic global data-create handlers:

```text
ext/modules/member/lib/member-on-data-create/member-on-data-create.php
function member_on_data_create($event) {}
```

Core 1.1 removes that broadcast. Resource side effects must be declared on the target resource `.meta`:

```json
{
  "events": {
    "create": ["membership-application-created"]
  }
}
```

Plain event names dispatch to module libraries by convention:

```text
ext/modules/member/lib/membership-application-created.php
function membership_application_created($event) {}
```

Use `job:<type>` when the work should be queued:

```json
{
  "events": {
    "create": ["job:application-email-created"]
  }
}
```

The `.jobs` resource is a core setup resource. On older installs it is created lazily the first time `job_enqueue()` runs.

Queued jobs are processed by:

```bash
php core/cli/nimbly.php jobs:run
```

The default run processes one eligible job, which keeps scheduler usage simple, for example one CLI call every few seconds.

Job handlers use the same single-file module library convention and are identified by their `_job` function suffix:

```text
ext/modules/member/lib/application-email-created.php
function application_email_created_job($job) {}
```

#### 5. Verify `.meta` fields

After migration, each previously pk-driven resource should look like this:

```json
{
  "fields": {
    "title":    { "name": "Title", "type": "name", "required": true, "slug": true },
    "url_slug": { "name": "URL slug", "type": "slug", "source": "title" }
  },
  "index": ["url_slug"]
}
```

- No `pk` key
- A `slug` type field for the URL slug (auto-computed, manually overridable)
- The slug field listed in `index`

If you already had a plain `text` field acting as the slug, change its `type` to `slug` and add `"source": "source_field"` so the admin auto-computes it. Then re-run reindex:

```bash
php core/cli/nimbly.php reindex articles
```

#### 6. Remove any direct calls to `data_update_pk()`

The function no longer exists. If any custom shortcode or module called it, remove that code. The UUID is immutable — use a slug field + index instead.

#### 7. Migrate email service config from `.services` to `.env`

Core 1.1 drops SMTP-via-`.services` for core-managed emails. Email delivery is now configured in `.env` and sent via a provider API (Resend by default). The password reset email is no longer sent inline — it is enqueued as a job and dispatched by the job runner.

Add the following to your `.env`:

```
MAIL_SERVICE=resend
MAIL_FROM=no-reply@yourdomain.com
MAIL_FROM_NAME=Your Site Name
RESEND_API_KEY=re_xxxxxxxxxxxx
```

If your project had a `.services` record with `tpl: email-password-reset`, it is no longer used. The `upgrade-11` command will warn you if such records are found.

Projects with no `.services` records need no action here.

### What you do NOT need to do

- **Rename existing record files.** UUIDs on existing records stay as they are (even the md5-derived ones from 1.0). They are just UUIDs now — their origin no longer matters.
- **Rewrite all templates.** Only `route.inc` files that resolved slugs to records need updating.
- **Re-import data.** The existing JSON record files are fully compatible.

---

## 19. Code Quality & Conventions

### Always use curly brackets

Every control-flow block — `if`, `else`, `foreach`, `while`, `for` — must use `{` and `}`, even for single-line bodies. This applies to both PHP and JavaScript.

```php
// correct
if ($value) {
    do_something();
}

// wrong
if ($value) do_something();
if ($value)
    do_something();
```

```javascript
// correct
if (value) {
    doSomething();
}

// wrong
if (value) doSomething();
```

PHP naming: **snake_case everywhere** — functions, variables, parameters, file names. No camelCase or PascalCase in PHP.

### Proper solutions over hacks

Always solve the underlying problem. If the right fix requires a refactor, do the refactor. Never paper over an issue with workarounds, conditional flags, or code that compensates for a broken assumption.

- If a template needs data that isn't available, fix the data loading — don't hardcode a fallback.
- If a field type doesn't support a use case, extend the field type — don't work around it in the template.
- If a layout breaks, fix the layout — don't hide the symptom with z-index tricks or overflow hacks.

A proper solution is always preferable to a hack, even when it takes more effort.

### Component architecture

When adding a self-contained feature to a project — a photo collage, an interactive map, a newsletter signup block, a countdown timer — build it as a component, not as inline code on a page.

In Nimbly, a component is a reusable template in `ext/tpl/<name>/`:

```
ext/tpl/photo-collage/index.tpl    ← template
ext/tpl/photo-collage/style.css    ← optional component CSS; import it from ext/theme.css
ext/tpl/interactive-map/index.tpl
```

Called from any page template with its shortcode name:

```
[#photo-collage#]
[#interactive-map location="Amsterdam"#]
```

Rules for components:

- **Self-contained** — all markup, logic, and scoped styles live inside the component directory. The page template only calls the shortcode.
- **Configurable via parameters** — use shortcode params for anything that varies per use (`[#map location="..." zoom="12"#]`). Do not hardcode values that belong to the caller.
- **No side effects** — a component should not assume what page it is on, what other components are present, or what CSS classes the parent defines.
- **Portable** — a well-built component can be copied to another `ext/` repo and used immediately without modification.

For most UI components a template is sufficient. Promote to a module (§14) only when you need to install a resource (e.g. an event system that creates an `events` resource on install) or register dedicated routes (e.g. `/event/(slug)/`). A photo collage, an interactive map, a countdown timer, a newsletter block — none of these need a module.

### Commit messages

Use the [Conventional Commits](https://www.conventionalcommits.org/) standard for commit messages. Keep messages short, specific, and professional. One line is almost always enough. No "Co-authored-by", no generated noise, no trailing metadata.

**Good:**
```
fix: prevent mobile h1 overflow and align hamburger
feat: add eventdates field with per-occurrence time and ticket info
style: reduce tablet section top padding to match mobile
```

**Bad:**
```
fix stuff
WIP
Update files
Fix mobile h1 overflow and hamburger alignment
Co-Authored-By: Claude <noreply@anthropic.com>
```

If more context is needed, add it as a second paragraph after a blank line — but the first line must stand alone and be specific.

---

## 20. Form field rendering pipeline

This section explains the full flow from a resource field definition to a rendered form input. Read this before building a new field type.

### Flow overview

```
.meta field definition
  ↓
render_field() — PHP (core/modules/forms/lib/render-field.php)
  ↓
_f.* template variables
  ↓
[#field-{type}#] template (core/modules/forms/tpl/field-{type}/index.tpl)
  ↓
Alpine.js form_data.{field}   ← x-model binding
  ↓
API POST/PUT on submit
```

### `render_field()` and the `_f.*` context

`render_field()` is the gateway. It:

1. Takes a field definition array, the field key, and optional value/store/source parameters
2. Spreads the entire field definition into `_f.*` via `set_variable_dot('_f', $def)` — every `.meta` attribute is automatically available in the template
3. Sets computed variables on top: `_f.key`, `_f.title`, `_f.value`, `_f.model`, `_f.required`, `_f.ai`, `_f.bg`
4. Dispatches to `[#field-{type}#]` — the template for that field type

`_f.model` is the Alpine.js `x-model` expression, computed as `{store}.{field}`. The default store is `form_data`. For i18n fields it becomes `form_data.{field}[{lang}]`.

Standard `_f.*` variables available in every field template:

| Variable | Contents |
|---|---|
| `_f.key` | Field name / HTML name attribute |
| `_f.title` | Display label (from `name` in .meta) |
| `_f.value` | Pre-populated value |
| `_f.model` | Alpine x-model expression, e.g. `form_data.email` |
| `_f.required` | Whether the field is required |
| `_f.bg` | Background color class for the floating label |
| `_f.*` (any) | All other field definition keys — `_f.accept`, `_f.options`, `_f.resource`, etc. |

### `form_data` — the Alpine.js store

`form_data` is the reactive Alpine.js object that holds all current field values. It is declared on the `<form>` element. Each field writes its value here via `x-model="[#_f.model#]"`.

On submit, `form_data` is spread into the API payload and POSTed to `/api/v1/{resource}`. Rich editor fields (HTML, gallery, image) write their values through a separate mechanism (`nb.edit.get_field_values()`), which is merged in automatically — field templates do not need to handle this.

### The simplest field template

`field-default/index.tpl` is the base for text-like fields:

```html
<div class="relative my-10">
    <input type="[#_f.type#]" value="[#_f.value#]" name="[#_f.key#]"
        x-init="[#_f.model#]=`[#_f.value#]`"
        x-model="[#_f.model#]"
        [#if _f.required=(not-empty) echo=required#]
        class="input input-bordered w-full" />
    <label class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]">
        [#_f.title#][#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>
```

### Adding a new field type

1. Create the template:
   ```
   core/modules/forms/tpl/field-{type}/index.tpl   ← core types
   ext/lib/field-{type}.php                         ← project-specific (shortcode wrapper only)
   ```

2. In the template:
   - Bind to Alpine via `x-model="[#_f.model#]"`
   - Seed the initial value with `x-init="[#_f.model#]='[#_f.value#]'"`
   - Use `[#_f.key#]` as the HTML `name` attribute
   - Access type-specific options via `_f.*` (e.g. `[#_f.accept#]` for file restrictions)

3. Register the type name in `.meta` — it is immediately usable once the template exists.

4. If the field manages complex state (e.g. a file picker, a media browser trigger), nest its own `x-data` inside the form's `x-data`. The outer `form_data` remains accessible from nested scopes.
