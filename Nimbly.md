# Nimbly — Implementation Reference

This document is the authoritative reference for implementing features in Nimbly. It is intended for AI agents and developers working on Nimbly projects. Follow these conventions exactly.

---

## 1. Project Structure

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
ext/uri/index.tpl          → /
ext/uri/about/index.tpl    → /about/
ext/uri/blog/(slug)/index.tpl → /blog/<anything>/
```

A route template typically loads modules and renders the page:

```
[#module user#]
[#html#]
```

### Reusable templates

Stored in `ext/tpl/<name>/index.tpl`. Called with their name as a shortcode:

```
[#hero-section#]
[#card title="Hello" image="img/photo.jpg"#]
```

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
[#fmt var=data.records json#]           → JSON encode
[#fmt var=myvar empty={}  json#]        → JSON with fallback
[#fmt val=item.date type=date fmt="d-m-Y"#]
[#fmt val=item.body type=html#]         → strips tags
[#fmt val=item.size type=bytes#]        → human-readable bytes
[#fmt val=item.created type=ago#]       → "3 days ago"
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
Outputs a translated UI label from `.po` files.

```
[#text Search#]
[#text Save_changes#]
[#text No_results_found#]
```

Translations live in `ext/data/.i18n/text.<lang>.po`.

#### `[#slug value#]`
Converts a string to a URL-safe slug.

```
[#slug [#get item.title#]#]
```

#### `[#fmt val=timestamp type=date fmt="d-m-Y"#]`
Formats a date. Accepts timestamp or date string.

#### `[#markdown#]`
Renders Markdown content.

---

### HTML page

#### `[#html#]`
Renders the full HTML page shell (doctype, head, body). Place at the end of route templates.

#### `[#module name1 name2#]`
Loads one or more modules. Common usage:

```
[#module user#]          → loads user authentication
[#module user forms#]    → user + form handling
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

#### `[#detect-language#]`
Returns the active language code using URL, preference, TLD, browser, and fallback detection.

#### `[#language#]`
Returns the currently active language code.

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

### .meta file

```json
{
  "fields": {
    "title": {
      "name": "Title",
      "type": "name",
      "slug": true,
      "required": true
    },
    "published": {
      "name": "Published",
      "type": "boolean"
    },
    "body": {
      "name": "Body",
      "type": "html",
      "buttons": "h2,h3,bold,italic,orderedlist,unorderedlist,quote,anchor",
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
  "pk": "title_slug",
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

### Field configuration

Common options per field:

| Key | Type | Description |
|---|---|---|
| `name` | string | Display label in admin |
| `required` | boolean | Validation |
| `admin_col` | boolean | Show in admin table (default: true) |
| `slug` | boolean | Generate `<field>_slug` (name type only) |
| `multi` | boolean | Allow multiple values |
| `i18n` | boolean | Translate per language |
| `accept` | string | File type restriction for image/file |

**HTML fields** must be configured as one of:

Simple (short text):
```json
"buttons": "bold,italic",
"admin_col": false
```

Rich (full content):
```json
"buttons": "h2,h3,h4,bold,italic,orderedlist,unorderedlist,quote,anchor",
"media": true,
"media_sizes": "sm-90,md-70,lg-60,xl-50,xxl-40",
"admin_col": false
```

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

### Resource conventions (required)

- All `name`/`title` fields must have `"required": true`
- List resources (partners, logos, cards) must include `sort_order` + `sort` config
- Fields not shown in overview must have `"admin_col": false`
- HTML fields must have explicit `buttons` config
- When using `slug: true` on a name field, set `"pk": "<field>_slug"`

### System fields (auto-managed)

Every record automatically has: `uuid`, `_created`, `_modified`, `_created_by`, `_modified_by`. Never define these in `.meta`.

---

## 5. Multi-language (i18n)

### Site config

`ext/data/.config/site`:
```json
{
  "languages": ["en", "nl"]
}
```

First language is the fallback.

### Routing

Each language has its own URL prefix: `/en/`, `/nl/`

Redirect root to detected language:

`ext/uri/index.tpl`:
```
[#redirect [#detect-language#]#]
```

### Resource translation

In `.meta`:
```json
"languages": ["en", "nl"]
```

Per field:
```json
"title": {
  "type": "text",
  "required": true,
  "i18n": true
}
```

Fields without `i18n: true` are shared across all languages.

### Static text

Use `[#text Key#]` in templates. Translation files:

```
ext/data/.i18n/text.en.po
ext/data/.i18n/text.nl.po
ext/data/.i18n/text.base.po   (merged, generated by build)
```

Format:
```po
msgid "Search"
msgstr "Zoeken"
```

Build:
```bash
npm run text-build
```

### AI-assisted translation for HTML fields

```json
"body": {
  "type": "html",
  "i18n": true,
  "ai_prompts": {
    "_all": [
      "The input and output are in HTML, properly escaped for safe use in an HTML editor.",
      "You preserve existing HTML structures including images, lists, links and other tags.",
      "You do not introduce raw < or > characters, use &lt; and &gt; instead."
    ],
    "en": ["You translate to English."],
    "nl": ["You translate to Dutch."]
  }
}
```

---

## 6. Routing

Routes are `.tpl` files in `ext/uri/`. The filename/folder structure maps directly to the URL.

Dynamic segments use parentheses:

```
ext/uri/blog/(slug)/index.tpl     → /blog/<anything>/
ext/uri/user/(id)/index.tpl       → /user/<anything>/
```

A route template sets up the page context:

```
[#module user#]
[#access feature=manage-content#]
[#set page-title="Blog"#]
[#html#]
```

The `main.tpl` in the same folder contains the page body rendered inside the HTML shell.

For routes with a `route.inc` (PHP logic):

```php
<?php
router_deny();
$parts = router_match(__FILE__);
if ($parts === false) return;
$slug = $parts[0];
load_library('data');
if (!data_exists('articles', $slug)) return;
set_variable('slug', $slug);
router_accept();
```

---

## 7. Build

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

## 8. Admin

The built-in admin is available at `/nb-admin/`.

The new admin (1.1+) uses DaisyUI components and these shortcodes from `core/modules/admin/lib/`:

- `[#get-resource-records resource=users role=table#]` — loads all records + filtered fields into `data.records` and `data.fields`
- `[#get-resource-record resource=users uuid=abc123#]` — loads a single record for editing with full i18n and encryption handling
- `[#get-resource-meta resource=users#]` — loads only field definitions into `data.fields`

The old admin templates are in `_dep_` folders and remain functional during the transition.

---

## 9. Anti-patterns

- Do not modify `core/`
- Do not add database concepts (tables, joins, foreign keys) — use resources
- Do not define system fields (`uuid`, `_created`, etc.) in `.meta`
- Do not create HTML fields without explicit `buttons` config
- Do not create resources without considering `admin_col`, sorting, and required fields
- Do not translate slugs
- Do not add `i18n: true` to non-content fields (images, booleans, sort_order)
