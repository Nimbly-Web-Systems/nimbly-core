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

---

### HTML page

#### `[#html#]`
Renders the full HTML page shell (doctype, head, body). Place at the end of route templates.

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

### System fields (auto-managed)

Every record automatically has: `uuid`, `_created`, `_modified`, `_created_by`, `_modified_by`. Never define these in `.meta`.

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

## 8. Forms

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

---

## 9. Rich content fields — end-to-end

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

---

## 10. Admin

The built-in admin is available at `/nb-admin/`.

The admin (1.1+) uses DaisyUI components. The following shortcodes from `core/modules/admin/lib/` handle data loading for admin views:

- `[#get-resource-records resource=users role=table#]` — loads all records + table-filtered fields into `data.records` and `data.fields`
- `[#get-resource-record resource=users uuid=abc123#]` — loads a single record for editing, with i18n and encryption handling
- `[#get-resource-meta resource=users#]` — loads only field definitions into `data.fields`

The old admin templates are in `_dep_` folders and remain functional during the transition to the new UI.

---

## 11. Anti-patterns

- Do not modify `core/`
- Do not add database concepts (tables, joins, foreign keys) — use resources
- Do not define system fields (`uuid`, `_created`, etc.) in `.meta`
- Do not create HTML fields without explicit `buttons` config
- Do not create resources without considering `admin_col`, sorting, and required fields
- Do not add `i18n: true` to non-content fields (images, booleans, sort_order, dates)
- Do not define `languages` on a resource without also defining it in site config
- Do not create incomplete or minimal resource schemas — always production-ready

---

## 12. Pending changes

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
