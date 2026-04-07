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

# 3. Install dependencies and build
bash -i -c "nvm use --lts && npm install && npm run build"
```

After this, the project is fully operational. Core and ext evolve independently.

### Updating core or ext from the admin

Both repos can be updated without touching the terminal. In the admin (`/nb-admin/`), navigate to **Settings** — there are separate **Update Core** and **Update Ext** buttons that run `git pull` on the respective repository. This is the standard way to deploy updates in production.

### Directory layout

```
core/          # Framework — never modify
  lib/         # Core shortcode implementations
  modules/     # Core modules (admin, forms, api, install, user)
  tpl/         # Core templates (html wrapper, etc.)
  uri/         # Core routes
ext/           # Your application — all custom work goes here
  data/        # Resources (.meta + JSON records)
  lib/         # Custom shortcodes
  modules/     # Custom modules
  tpl/         # Reusable templates
  uri/         # Route templates
  static/      # Built assets (output of npm build)
css/           # CSS source
js/            # JS source (JSX/Alpine)
```

**Rule:** Never modify `core/`. All customization lives in `ext/`.

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

A route template typically loads modules and renders the page:

```
[#module user#]
[#html#]
```

The `main.tpl` in the same folder contains the page body, rendered inside the HTML shell.

### Reusable templates

Stored in `ext/tpl/<name>/index.tpl`. Called with their name as a shortcode:

```
[#hero-section#]
[#card title="Hello" image="img/photo.jpg"#]
```

### route.inc

For routes that need PHP logic (loading data, access control, dynamic routing), add a `route.inc` alongside `index.tpl`:

```php
<?php
$parts = router_match(__FILE__);
if ($parts === false) return;
$slug = $parts[0];
load_library('data');
if (!data_exists('articles', $slug)) return;
set_variable('slug', $slug);
router_accept();
```

#### Router functions

| Function | Description |
|---|---|
| `router_match(__FILE__)` | Matches the current URL against this route's dynamic segments. Returns an array of captured values (one per `(param)` in the path), or `false` if the URL does not match. Always call first. |
| `router_accept()` | Signals that this route.inc accepts the request. The template engine proceeds to render `index.tpl`. |
| `router_deny()` | Signals rejection — the router continues looking for another matching route. Calling `return` without `router_accept()` has the same effect. |

The standard pattern: call `router_match()`, validate the result, load and validate any data, then call `router_accept()` only when everything checks out. If anything fails, just `return` — the request falls through to the next route or a 404.

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
[#set count=0 append=" "#]     → appends to existing value
[#set key=value session#]      → persists in session
```

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
```

Types: `text`, `html`, `date`, `ago`, `json`, `bytes`, `boolean`, `image`, `password`

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

This pattern requires `[#module user#]` to enable inline admin editing.

#### `[#get-img-html image sizes="...#]`
Renders a responsive `<img>` tag with `srcset` and `sizes`. Requires the `images` module to be loaded first.

```
[#module images#]
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
| `page-title` | Sets the `<title>` tag |
| `body-classes` | Adds CSS classes to the `<body>` tag |
| `page-settings-link` | Adds a shortcut link in the Nimbly admin bar pointing to the admin edit page for the current record. Use this on detail pages to allow quick admin access from the frontend. Example: `[#base-url#]/nb-admin/articles/[#record.uuid#]` |

```
[#set page-title="[#get record.title#]"#]
[#set page-settings-link="[#base-url#]/nb-admin/event/[#record.uuid#]"#]
[#set body-classes=site-body#]
[#html#]
```

#### `[#module name1 name2#]`
Loads one or more modules. Common usage:

```
[#module user#]              → loads user authentication
[#module user forms#]        → user + form handling
[#module user admin forms#]
```

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

#### `[#logged-in#]`
Returns `"logged-in"` if a user session is active.

```
[#if logged-in=(empty) redirect=login#]
```

#### `[#feature-cond features=name#]`
Conditionally renders content based on whether the current user has a specific feature/permission. Requires `[#module user#]`.

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
Sends an email using a template and a service config stored in `.services`. See the email module documentation for setup.

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
| `name` | Text with optional slug generation |
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
| `pk` | Primary key field name |
| `sort` | Default sort (`field`, `flags`: `string`/`numeric`, `order`: `asc`/`desc`) |
| `validate` | Validation rules (e.g. `natural-short-text`) |
| `languages` | Enabled languages for this resource |
| `ai_prompts` | Per-field AI translation instructions |
| `actions` | Object with a `url` key — adds a "View" button in the admin record list pointing to the frontend URL of the record. Shortcodes are evaluated in the URL value. Example: `{"url": "[#base-url#]/articles/[#record.title_slug#]"}` |
| `splitdir` | Boolean. When `true`, records are stored in a two-level subdirectory tree by UUID prefix for filesystem performance at scale (> ~10,000 records). See §12 API — Scalability. |

### System fields (auto-managed)

Every record automatically has: `uuid`, `_created`, `_modified`, `_created_by`, `_modified_by`. Never define these in `.meta`.

### Hidden resources

Resources whose names begin with `.` are hidden from the admin data management UI by default (Unix hidden-file convention). They remain fully accessible via the data library and API.

Built-in hidden resources: `.config`, `.content`, `.routes`, `.i18n`.

Custom hidden resources follow the same convention — name them with a leading dot to keep them out of the admin overview.

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
- Is this linked from a URL? It needs a primary key
- Does content need to be translated? Add `languages` and `i18n` per field

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
npm run text-build
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
- Never translate slugs or primary key fields
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

Nimbly ships a CLI at `core/cli/nimbly.php`. The npm scripts are the preferred way to invoke it:

```bash
npm run setup
npm run create-user
npm run install-module <name>
```

Equivalent direct invocations:

```bash
php core/cli/nimbly.php setup
php core/cli/nimbly.php create-user
php core/cli/nimbly.php install-module <name>
php core/cli/nimbly.php help
```

### Commands

#### `setup`
First-time site initialisation. Safe to re-run — existing files and records are never overwritten.

What it does:
- Creates `.env` with `BASE_PATH` and a generated `PEPPER`
- Generates `.htaccess` from template
- Creates the `ext/` directory scaffold (`data/`, `static/`, `lib/`, `modules/`, `tpl/`, `uri/`, temp dirs)
- Creates `.config/site`, the `.content` resource, core `.routes` records, and default roles (`admin`, `editor`)
- Creates the `users` resource and an initial admin user

Prompts: **Site name**, **Admin email**, **Admin password**. Steps that are already complete are skipped silently.

**Non-interactive (CI/CD):** Set environment variables to skip all prompts:

| Variable | Description |
|---|---|
| `BASE_PATH` | URL base path (default `/`) |
| `PEPPER` | Encryption pepper — set to reuse an existing value; omit to auto-generate |
| `EXT_REPO` | Git remote URL for the ext repo (written to `ext/readme.md`) |
| `SITE_NAME` | Site name written to `.config/site` |
| `ADMIN_EMAIL` | Initial admin user email |
| `ADMIN_PASSWORD` | Initial admin user password (min 8 chars) |

```bash
SITE_NAME="My Site" ADMIN_EMAIL=admin@example.com ADMIN_PASSWORD=secret123 npm run setup
```

#### `create-user`
Creates an additional user account. Prompts for email, role, and password interactively. Available roles are read from `ext/data/roles/`. The user is always also assigned the `user` role. Requires setup to have been run first.

#### `install-module`
Runs a module's `.install.inc` script. Looks in `ext/modules/` first, then `core/modules/` as fallback. Requires setup to have been run first.

```bash
npm run install-module event
```

---

## 8. Build

```bash
npm run build       # full build: Tailwind + CSS + JS + i18n
npm run tw          # watch Tailwind
npm run tw-build    # build Tailwind once
npm run css-build   # build CSS (esbuild)
npm run js-build    # build JS (esbuild)
npm run text-build  # merge .po translation files
npm run up          # start Docker dev environment
npm run init        # initialise a new ext/ setup
```

Built files go to `ext/static/`. Always run build after changing CSS, JS, or Tailwind classes.

---

## 9. Forms

The forms module handles front-end form submissions with CSRF protection, honeypot spam filtering, validation, and Alpine.js-powered submission via the REST API.

### Loading the module

Always load the forms module in the route template:

```
[#module user forms#]
[#html#]
```

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

For forms that need server-side processing beyond writing to a resource (e.g. file imports, sending email), add handler files alongside the route:

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

### Step 4 — Load the user module

The inline editor is activated by the user module. Always load it:

```
[#module user#]
[#html#]
```

Without `[#module user#]`, logged-in users will see raw HTML instead of the editor.

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
  [#module images#]
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

The old admin templates are in `_dep_` folders and remain functional during the transition to the new UI.

---

## 12. API

The Nimbly API is automatically available for every resource — no configuration required. Create a resource and the REST API is live.

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

Custom shortcodes live in `ext/lib/<name>/` as a PHP file with the same name:

```
ext/lib/prepare-events/prepare-events.php
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

---

## 14. Modules

A module is a self-contained feature that bundles its own routes, templates, libraries, and install logic. Use a module when a feature ships as a reusable unit — e.g. an event system, a shop, a blog — rather than as a loose set of pages.

### Module directory structure

```
ext/modules/<name>/
  .install.inc     # Install script — runs once when the module is installed
  lib/             # Shortcode libraries scoped to this module
  tpl/             # Templates scoped to this module
  uri/             # Routes scoped to this module (merged into the URL space)
```

Routes inside `modules/<name>/uri/` are served at the same paths as if they were in `ext/uri/`. A route at `ext/modules/event/uri/event/(slug)/index.tpl` is accessible at `/event/<slug>/`.

### Loading a module

```
[#module event#]
```

Placing this in any template loads the module's libraries and routes. The module is only initialised once per request.

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

### Slug-to-UUID routing (current pattern)

When a resource uses a slug as its primary key (`"pk": "title_slug"`), the slug stored in the URL is a human-readable string, but records are keyed by its MD5 hash. Use `md5_uuid()` in `route.inc` to resolve the slug back to the record:

```php
<?php

$parts = router_match(__FILE__);
if ($parts === false || count($parts) !== 1) return;

$slug = $parts[0];
load_library('md5');
$uuid = md5_uuid($slug);

load_library('data');
if (!data_exists('events', $uuid)) return;

$record = data_read('events', $uuid);
load_library('set');
set_variable_dot('record', $record);

router_accept();
```

> **Note:** MD5-based slug routing is a transitional pattern. The pending index system (see §16 Pending changes) will replace this. Do not design new resources around `pk: title_slug`; this pattern documents how existing modules work.

---

## 15. Anti-patterns

- Do not modify `core/`
- Do not add database concepts (tables, joins, foreign keys) — use resources
- Do not define system fields (`uuid`, `_created`, etc.) in `.meta`
- Do not create HTML fields without explicit `buttons` config
- Do not create resources without considering `admin_col`, sorting, and required fields
- Do not add `i18n: true` to non-content fields (images, booleans, sort_order, dates)
- Do not define `languages` on a resource without also defining it in site config
- Do not create incomplete or minimal resource schemas — always production-ready

---

## 16. Pending changes

The following areas are under active development and will be updated here as they are finalized:

- **Indexes** — slugs as primary keys (`pk: title_slug`) are being replaced by an index system. Do not design new resources around slug-based primary keys. This section will be updated when the new approach is settled.
- **DaisyUI migration** — Tailwind Elements is being phased out. The admin is already on DaisyUI v3. Frontend templates that still use Tailwind Elements components (modals, dropdowns, etc.) are being migrated. Do not introduce new Tailwind Elements usage; use DaisyUI equivalents instead. Frontend DaisyUI component patterns will be documented here once the migration is complete.
- **Resource display names** — the `resource-name` shortcode currently derives singular/plural from the slug (strips trailing `s`, handles `ies→y`). Plan: allow `.meta` to define `name_singular` and `name_plural` with optional i18n:
  ```json
  "name_singular": { "en": "Client", "nl": "Klant" },
  "name_plural":   { "en": "Clients", "nl": "Klanten" }
  ```
  `resource-name` would use these when present and fall back to slug-based logic otherwise.
- **Shortcode single-file format** — currently every shortcode requires a directory (`lib/my-shortcode/my-shortcode.php`). A planned improvement is to allow a single-file fallback (`lib/my-shortcode.php`) for simple shortcodes that don't need a template. Until then, always use the directory format.
