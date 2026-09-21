# Nimbly architecture review: editor pages and navigation

## 1. Executive recommendation

Adopt a small, optional content-management capability combining:

- **Page records** for editor-created informational and campaign pages.
- **Developer-declared page types** rendered through ordinary Nimbly templates.
- **Independent navigation data** consumed by developer-owned navigation templates.
- **Existing filesystem routes and domain resources**, continuing to work naturally.

Do not generate files under `ext/uri` when editors create pages.

The fundamental abstraction should be **a content record with a public address and an approved rendering contract**. A filesystem route is one way to implement a public address; it need not represent every address.

Your proposed direction—dynamic pages plus editable navigation—is sound. Three adjustments matter:

1. Navigation hierarchy, URL hierarchy, and content relationships must remain separate.
2. “Developer routes contain behavior; editor pages contain content” is useful guidance, but too rigid as an architectural rule.
3. Putting mutable state under `ext/data` supports future scalability; it does not make the current data layer distributed or transactional.

This strengthens Nimbly’s positioning: developers define the system’s capabilities and design language; editors operate within them without requiring code changes.

**Evidence boundary.** This review used targeted sections of `NIMBLY.md` and inspected routing, resource persistence, inline content rendering, multilingual handling, SEO, sitemap generation, API authorization, and deployment synchronization. At review time, local Core `b23c6f7c` and application `a50127f6` matched the remote development tips. No files, branches, or deployments were changed during the review. Production data was not merged or audited; this is a platform architecture review, not a diagnosis of GCNE’s implementation.

## 2. Mental model

| Ownership | Concepts |
|---|---|
| Developer | Filesystem routes, application behavior, resource schemas, page-type declarations, templates, navigation slots, permitted URL areas, authorization policy |
| Editor | Page content, titles, localized addresses, publication state, approved type selection, navigation links, labels, ordering and hierarchy |
| Runtime | Request resolution, authorization enforcement, canonical URLs, target resolution, visibility filtering, derived indexes and caches |

The durable identities are:

- **Page:** a stable UUID.
- **Page address:** a changeable, localized path.
- **Page type:** a stable developer-declared identifier.
- **Navigation item:** an independent identity referencing a destination.
- **Navigation slot:** a developer-declared place where editable navigation is consumed.

A page is neither its URL nor its menu entry.

Also refine the existing ownership invariant: editor actions should normally write only under `ext/data`, but **not everything inside `ext/data` is editor-owned**. Resource `.meta` files define schema and behavior; `.routes` describes application routing; `.tmp` contains derived state. Filesystem location alone cannot define authority.

## 3. Alternatives considered

| Approach | Advantages | Disadvantages | Decision |
|---|---|---|---|
| Project-specific resources | Strong semantics, natural validation, excellent for domain behavior | Awkward for unrelated informational pages; repeated page plumbing | Keep for genuine domain concepts |
| Generic page records | Immediate publishing, stable identity, content/code separation, works with persistent storage | Requires address validation, rendering declarations and editorial UI | Adopt |
| Editor-generated physical routes | Familiar route inspection; can reuse existing template scaffolds | Turns editorial publishing into code deployment; complicates multi-node consistency, rename/delete and ownership | Reject as runtime authoring model |
| Hybrid routes, domain resources and pages | Preserves existing projects and supports different needs directly | Requires explicit precedence and boundaries | Recommended architecture |
| CMS-backed static generation | Immutable output, CDN distribution, reproducible releases | Content changes require build/publication workflow; preview and synchronization become separate concerns | Possible delivery option later |
| Universal page/tree abstraction | Unified editor surface | Forces products, routes and content into a CMS-shaped model | Reject |

### Why generating routes is the wrong default

A generated template containing only a trusted scaffold can be safe in isolation. The objection is not that every generator necessarily creates a code-execution vulnerability.

The problem is the lifecycle it introduces:

- Which instance writes the file?
- How do other instances receive it?
- Which release owns it?
- What happens when a deployment replaces it?
- Does renaming a page require moving code and associated content keys?
- Can rollback restore deleted scaffolds without restoring unrelated content?

A central service could generate, commit, build and deploy those files. That is a legitimate publishing pipeline—but substantially more machinery than resolving a page record.

Generating **derived static output** from records later is different. The records remain authoritative; the generated output can be discarded and rebuilt.

### Why domain resources remain important

If “Themes” has meaningful fields, relationships or behavior, it should remain a resource. A campaign page can reference a theme.

Do not turn events, products, applications or membership records into generic pages merely because they have URLs.

## 4. Comparison with other systems

| System | Relevant architecture | Lesson for Nimbly |
|---|---|---|
| WordPress | Classic themes register navigation locations; editors compose menus from pages, posts, categories and URLs. Developers also provide selectable page templates. | Adopt independent menus and approved templates. Those concepts do not require adopting a general site editor. |
| Craft CMS | Entries receive URLs through section URI rules and render through designated templates. Structures add hierarchical organization. | Reuse the record → address → template relationship. Make hierarchy opt-in instead of assuming every hierarchy controls URLs. |
| Statamic | Navigation composes entry references, arbitrary URLs and text nodes; referenced entries retain their own URLs. Navigation trees are stored as content. | Closest fit for navigation: a menu arranges destinations without owning them. |
| Sanity | Developer-defined schemas describe content; an optional page builder composes explicitly allowed objects or references. | Model meaning and constrain available choices. Page creation does not require exposing every design-system component. |
| Decap CMS with a static-site generator | Editors modify content in Git; a build/deployment workflow produces the site. | Git-based publishing is valid when deliberately chosen. It is a deployment model, not a reason to generate runtime application source. |

Sources: [WordPress menus](https://developer.wordpress.org/themes/classic-themes/functionality/navigation-menus/), [WordPress page templates](https://developer.wordpress.org/themes/classic-themes/templates/page-template-files/), [Craft entries](https://craftcms.com/docs/5.x/reference/element-types/entries), [Craft routing](https://craftcms.com/docs/5.x/system/routing), [Statamic navigation](https://statamic.dev/content-modeling/navigation), [Sanity structured page building](https://www.sanity.io/docs/developer-guides/how-to-use-structured-content-for-page-building), [Decap architecture](https://decapcms.org/docs/intro/).

The useful common pattern is **structured content with developer-controlled rendering**. The avoidable complexity is making content creation imply arbitrary layout design, or making one global page tree govern every application concern.

Headless separation is also instructive: content identity and storage need not depend on a frontend’s source tree. Nimbly can preserve that boundary without requiring an external CMS or separate frontend service.

## 5. Proposed Nimbly model

### Pages and existing routes coexist

The boundary should be based on the **authoring contract**, not whether a page contains any behavior:

- A filesystem route supports an implementation developers manage directly.
- An editor page instantiates a developer-approved content contract.
- A domain-resource route presents a record whose identity and semantics belong to that domain.

An editor-created campaign may contain a developer-built signup form. A static “About” route may be mostly editable content. Both are valid.

Do not convert either merely to enforce a conceptual taxonomy.

### Page types

A page record stores `type: "standard"` or `type: "campaign"`, not a template pathname.

A developer declaration maps that identifier to:

- An editor-facing name and description.
- An approved Nimbly template.
- Applicable fields and validation.
- Any permitted semantic settings.

For the initial implementation, use one explicit, source-controlled declaration in the application’s pages module, for example `ext/modules/pages/page-types.json`. Core loads this through the optional pages capability. Do not discover editor-selectable types by scanning every template.

The declaration references templates such as `page-standard` or `page-campaign`; editors see “Information page” or “Campaign page.”

Use existing resource field definitions and form rendering. Start with a shared schema. Do not introduce a second general schema language merely to support two templates.

Template filenames, PHP callbacks, Tailwind classes and shortcode expressions must not be editable record values. A semantic option such as “related theme” is appropriate; a configurable grid implementation is not.

Types are not freely interchangeable once populated. For MVP, select the type at creation; type conversion requires an explicit later capability.

### Routing and collisions

The current flow already gives ordinary exact routes priority, then tries ordered `.routes` handlers. `router_match()` matches a fixed number of segments. See [request dispatch](../core/lib/run.php) (`run_uri()`) and [dynamic routing](../core/lib/router.php).

Add an **opt-in page-resolution step after existing dynamic routing and before the final 404**. It resolves the normalized complete path, supporting arbitrary nesting without generating handler files for every depth.

The contract is:

1. Existing exact application routes retain precedence.
2. Existing dynamic route handling retains precedence.
3. Editor pages resolve only within developer-enabled URL areas.
4. Otherwise return the existing 404.

Precedence alone is insufficient. A rejected application route must not accidentally expose an editor page under the same protected address.

Therefore:

- Reserve framework endpoints and application-owned dynamic route patterns.
- Reject page addresses overlapping exact routes or reserved patterns.
- Allow `/nl/themes/new-campaign/` beneath a static `/nl/themes/` unless that subtree is explicitly reserved.
- Treat the same normalized address in the same language as a collision.
- Check addresses on save and again against a candidate release before deployment.

If an editor attempts `/nl/campaign-x/` and an exact route exists, saving that address fails with a clear explanation.

If a later code release introduces that route, the release check reports the collision. Runtime precedence remains deterministic: the code route wins. Do not silently delete the page record or render its navigation link as though it still owns that address.

Arbitrary route handlers can implement rules that cannot be inferred safely from filenames. Projects using them must declare the corresponding reserved URL areas. Do not execute handlers during validation to discover ownership.

### URLs and hierarchy

Store a complete installation-relative path per language.

A “create underneath this section” action proposes a prefixed path. It does not require a parent-page database relation.

Moving a navigation item never changes the URL. Renaming the page title never silently changes the URL.

This avoids conflating three different actions:

- Reorganizing a menu.
- Moving a public address.
- Changing a content relationship.

### Navigation

Use a hidden resource, `.navigation`, with a dedicated editor.

Hidden status keeps it out of the generic resource overview; it is **not** a security mechanism. Nimbly explicitly documents hidden resources as accessible through the data library and API. See [NIMBLY.md](../NIMBLY.md), “Resources > Hidden resources.”

Developers declare slots and supported depth. Editors manage entries in those slots. Rendering remains ordinary templates.

Do not create separate `main` and `mobile` datasets merely because their rendering differs. The same tree should normally feed desktop and mobile templates. Separate slots are appropriate only when their content differs intentionally.

A slot such as `main-themes` is useful when the developer wants only that subsection editable. Editors need not own the entire header.

Application action menus—account actions, logout, permission-dependent controls—can remain code-owned.

### Permissions and publication

Reuse Nimbly’s existing resource permissions and access checks. Do not create parallel roles or an approval subsystem.

For MVP, consistent with the selected preference:

- New pages begin unpublished.
- Authorized page editors can publish and unpublish.
- Editing a published page changes the live content immediately.
- Navigation editing is granted separately.
- Public requests cannot read unpublished pages through the page resolver.
- Generic resource APIs do not receive anonymous access to raw page records.

If separate author/publisher roles become necessary, publication must be enforced on every write path. Hiding a checkbox is insufficient.

### Multilingual handling

Use Nimbly’s existing field-level translation model: one UUID, translated fields represented as language maps.

Localize title, path, publication state, SEO fields and content. Keep the page type shared initially.

Publication and address resolution must use the **exact requested language**. Nimbly’s normal text fallback must not make an unpublished translation public.

Navigation trees are stored separately per slot and language. This permits genuinely different language-site structures without forcing synchronized hierarchies.

Build language-switch links and alternate metadata only for available published variants, using the existing `i18n-url` mechanism.

### Renaming and deletion

Keep UUIDs stable across title and URL changes.

For URL changes, retain previous addresses on the page record and redirect them directly to its current localized address. Reserve those aliases against reuse. This avoids redirect chains and allows address plus history to change in one record write.

Unpublishing disables public page resolution and its aliases. Navigation filters the destination out but retains the editorial entry so republishing restores it.

Deleting a navigation item does not delete its page. Deleting a page should be explicit and show known navigation references. Invalid targets remain visible as warnings in the editor and are omitted publicly.

Do not promise to discover every incoming link inside arbitrary rich text or on other websites.

## 6. Data model

The initial model needs only two new resources.

| Resource | Purpose |
|---|---|
| `pages` | Ordinary editor-managed page records |
| `.navigation` | One navigation document per declared slot and language |

Developer-owned type and slot declarations remain source-controlled. Resource schemas retain their existing `.meta` representation and are installed through established mechanisms.

### Page record

Illustrative shape; IDs are shortened for readability:

```json
{
  "uuid": "page-123",
  "type": "standard",
  "title": {
    "nl": "Zomercampagne",
    "en": "Summer campaign"
  },
  "path": {
    "nl": "nl/themas/zomer",
    "en": "en/themes/summer"
  },
  "published": {
    "nl": true,
    "en": false
  },
  "body": {
    "nl": "<p>...</p>",
    "en": "<p>...</p>"
  },
  "seo_title": {
    "nl": "Zomercampagne"
  },
  "seo_description": {
    "nl": "..."
  },
  "previous_paths": {
    "nl": ["nl/zomeractie"],
    "en": []
  }
}
```

Content and SEO values have one authoritative home: the page record.

Do not duplicate these into `.content` or `.config` just to mimic an existing static route. Adapt the page renderer and settings form to the page resource.

Existing static routes continue using their current storage. Nimbly already supports `page-content-key`, which is useful when a stable key is needed during an intentional migration. See [content-key resolution](../core/lib/url-key.php).

### Navigation record

```json
{
  "uuid": "main-nl",
  "slot": "main",
  "language": "nl",
  "items": [
    {
      "id": "item-1",
      "label": "Thema’s",
      "target": {
        "kind": "internal_url",
        "path": "nl/themas"
      },
      "children": [
        {
          "id": "item-2",
          "label": "Zomer",
          "target": {
            "kind": "page",
            "id": "page-123"
          },
          "children": []
        }
      ]
    }
  ]
}
```

Support page references, internal URLs, external URLs and non-link group labels. Page references follow URL changes; raw URLs do not.

A named-route reference system would improve developer-route renaming, but the inspected router is path-based. Do not invent a universal route registry for this MVP.

### Why one navigation document?

A menu is small and normally edited as a tree. One document allows a reorder to become one coherent write.

A record per node provides finer permissions and independent editing, but requires coordinated writes to preserve parent/order consistency. That is unnecessary initially.

The document still uses the existing resource layer. It is not an unmanaged JSON file beside the templates.

One concrete implementation requirement: saving the tree must **replace the submitted tree**, including deletions. Nimbly’s current `data_update()` recursively merges values; that is not automatically correct for replacing nested menu arrays. See [resource updates](../core/lib/data.php) (`data_update()`).

Use stale-edit detection with an atomic check-and-write operation. Atomic file replacement alone does not prevent lost updates.

## 7. Rendering flow

### Page requests

```text
Request
  → existing exact route
  → existing dynamic handlers
  → optional page resolver
      → check enabled area and reserved routes
      → find exact localized path or historical alias
      → enforce publication and access
      → load approved page type
      → establish page/resource and language context
      → render normal Nimbly templates
      → existing resource-backed inline editing
  → existing 404 when unresolved
```

The resolver provides the page UUID, localized fields and ordinary HTML-shell metadata.

Use the existing rich-content pipeline and resource-field editing identifiers. Nimbly already documents resource-backed inline editing; a second content editor is unnecessary. See [NIMBLY.md](../NIMBLY.md), “Rich content fields — end-to-end.”

Public resolution must not create page records or content records as a side effect. This deserves a regression test because the current `get-html` fallback can create missing resource data. See [HTML field rendering](../core/lib/get-html.php).

### Navigation rendering

```text
Template requests slot + language
  → load navigation document
  → resolve destination references
  → filter invisible/unavailable targets
  → calculate current-item and ancestor state
  → return normalized tree
  → application template renders it
```

Core returns data, not a prescribed menu layout.

If a linked parent becomes unavailable, omit that branch in MVP rather than unexpectedly promoting its children. A persistent non-link heading should be modeled explicitly as a group.

A page can appear in zero, one or several menus, with different labels. Menu visibility does not control page publication or authorization.

## 8. Scalability and deployment implications

### The proposed model fits disposable application instances

Each release contains the same routes, type declarations, templates and assets. All instances resolve pages from shared authoritative data.

Publishing content requires no code deployment. Adding a new page type does require a deployment.

A rolling release must preserve compatibility: deploy support for a new type everywhere before editors can use it; do not remove a type while published records still reference it.

Code rollback and content rollback are separate operations. Rolling back application code must not silently replace newer editorial content.

### Nimbly is not yet storage-independent

The inspected implementation exposes specific future work:

| Area | Current evidence | Implication |
|---|---|---|
| Concurrent edits | Updates read, merge, validate and rewrite; atomic rename protects file completeness | Competing writers can still overwrite each other |
| Uniqueness | Validation checks existing values before writing; localized objects are skipped by scalar uniqueness validation | Localized address reservation needs explicit atomic enforcement |
| Index consistency | Records and indexes are updated separately | No multi-file transaction guarantee |
| Caching | Resource caches use filesystem timestamps and deletion | Shared filesystem visibility and invalidation need verification |
| Scheduler | Schedule lock is under the system temporary directory | Separate containers do not automatically share ownership |
| Jobs | Runner lock is also temporary-directory based | Multiple instances need coordinated job claiming or a single worker owner |
| Storage access | Paths, directory traversal and filesystem indexes appear throughout the data layer | An object-store or database adapter is not currently a drop-in replacement |

Sources: [data writes and uniqueness validation](../core/lib/data.php) (`_data_write_file_atomically()`, `_data_validate_unique()`), [schedule locking](../core/cli/schedule.php), [job locking](../core/lib/job.php) (`job_run_queued()`).

A shared filesystem may be a practical intermediate deployment. Its rename, lock, timestamp and visibility semantics must be tested; “shared volume” is not a consistency guarantee.

Future storage work should keep page/navigation code using resource APIs, establish atomic conditional writes and uniqueness, coordinate background ownership, and separate disposable caches from authoritative data. Do not build that entire platform before enabling pages.

### Git requires particular care

Nimbly documents both immutable content deployment and live editing with synchronization. These are distinct operating modes; pages should work in either.

One significant observation: `ext:sync` currently stages the entire worktree with `git add -A`, not only `ext/data`. The editor/code boundary is therefore a convention, not a restriction enforced by that command. See [synchronization](../core/cli/ext_sync.php).

Generating editor-owned routes would make that ambiguity worse.

For future multi-node deployments, use one synchronization owner if Git export remains desirable. Multiple application instances must not independently treat their local Git checkout as the authoritative shared content database.

### SEO integration

Server-rendered records can provide the same indexable HTML as physical routes.

Reuse existing canonical and sitemap mechanisms. However, the current sitemap resource check tests publication before resolving language variants; a nonempty publication map is not a per-language publication decision. See [sitemap resource processing](../core/lib/sitemap.php) (`sitemap_resource_entries()`).

Extend that mechanism to include only published localized page addresses. Sitemap membership must remain independent of navigation membership.

## 9. Migration path

1. **Enable one navigation slot.** Translate existing hardcoded entries into data, retaining the current template and markup.
2. **Verify editorial independence.** Labels and hierarchy become editable without changing destinations or route files.
3. **Add the optional pages capability.** Introduce one standard type and the smallest additional campaign type actually needed.
4. **Enable appropriate URL areas.** Preserve existing routes and reserve application-owned patterns.
5. **Connect navigation to new pages.** Use UUID references for editor-created pages.
6. **Convert existing static pages only when beneficial.** Preserve their addresses and intentionally migrate content/settings; do not bulk-convert routes.

Structured lists can continue reading domain resources directly. If a menu should always list every published theme, a developer-defined query may be simpler than manually maintaining equivalent navigation entries.

Avoid storing the same ordering and membership twice.

## 10. Minimal viable implementation

### Build first

- Optional Core page/navigation capability with project-owned declarations and templates.
- `pages` resource with localized content, address and publication fields.
- Explicit type allowlist; no arbitrary template names from editor input.
- Page fallback resolution, reserved URL areas and collision validation.
- Independent `.navigation` documents and a focused tree editor.
- Drag/drop plus keyboard-accessible move/reparent controls.
- Existing inline editing and existing authorization integration.
- Stable page links, URL-history redirects, canonical metadata and sitemap integration.
- Atomic address validation/write and stale-edit protection for navigation saves.
- Simple live editing, as selected: no separate working copy of published content.

This is framework functionality because it is reusable across Nimbly applications. The project-specific designs, schemas beyond the shared fields, and slot configuration belong in `ext`.

### Public interfaces

Keep the initial contract small:

- Developer declarations for allowed page types, navigation slots and URL areas.
- A page resolver used by request dispatch.
- A page-URL lookup by UUID and language.
- A navigation loader returning normalized tree data.
- Editor operations routed through existing authentication and resource mechanisms, with the required validation and write guarantees.

These are proposed interfaces, not claims that equivalent APIs already exist.

### Acceptance tests

The implementation is complete when:

- An editor creates and publishes `/nl/themas/campaign/` without changing application source files.
- A page can be absent from navigation or appear twice with different labels.
- Reordering navigation changes neither page identity nor URL.
- Renaming a URL preserves content, updates page-reference links and redirects the old address.
- Static routes, dynamic routes and protected namespaces retain their behavior.
- Unpublished translations cannot appear through page lookup, navigation, sitemap or anonymous API access.
- Missing page types produce an editorial error and never execute record-supplied template paths.
- Concurrent address claims cannot both succeed.
- A stale navigation save is rejected; deleting a nested item actually removes it.
- Disabling the capability leaves existing sites working.
- Existing desktop/mobile navigation behavior and keyboard interaction remain correct.

Use focused PHP and routing tests, plus browser tests for the navigation editor and inline-editing behavior. No runtime tests were executed for this review.

### Defer

- Working drafts of published pages and revision comparison.
- Approval workflows and scheduled publication.
- Arbitrary section composition or page builders.
- Universal named-route references.
- A global content tree.
- Multi-node storage conversion.
- Static export and CDN publication pipelines.

## 11. Risks / unresolved decisions

The architecture can be chosen now. These boundaries need explicit treatment during implementation:

**Page settings integration.** Existing static-page settings use `.config`, while rich content can use `.content`. Managed pages should use their own record as the source of truth. Reuse the form components without allowing conflicting copies of title or SEO settings.

**Write enforcement.** After-save lifecycle events cannot enforce address uniqueness before persistence. Required validation and concurrency protection must cover admin, API and inline-editing writes—not only the new UI.

**Address normalization.** Follow Nimbly’s existing canonical URL convention and apply the same normalization during save, lookup and collision checks. Reject ambiguous path forms rather than inventing a competing URL system.

**Navigation document contention.** Whole-tree saves are appropriate for small menus but require conflict detection. Per-node storage becomes worth reconsidering only with demonstrated collaboration or scale needs.

**Rich-content trust.** Approved page types do not make arbitrary HTML safe. Verify the existing write-time sanitization and output path; `get-html`’s tag filtering alone is not proof of safe attribute and URL handling.

**Operational history.** Current Git synchronization is not equivalent to an editorial revision system. MVP rollback uses established backup/Git operations under operator control; do not advertise independent page revisions until they exist.

**Project-specific choices.** GCNE’s actual URL reservations, initial type fields and editable navigation slots require inspection of that application. This review deliberately does not invent them.

The storage, scheduler, synchronization and sanitization observations were identified but left unchanged. They are not authorization to broaden the page/navigation implementation into unrelated remediation.

## 12. Final verdict

Choose **hybrid routing with data-backed editor pages, developer-declared page types, and independent data-backed navigation**.

Reject editor-generated `ext/uri` files as the normal publishing mechanism. Keep domain resources where they express real domain concepts, and leave existing routes intact.

The lasting architectural rule is:

**Editors create content and arrange links; developers define what those things can do and how they render.**

That gives communication teams the autonomy they need while preserving Nimbly’s atomic design model, application flexibility and clean separation between content changes and software releases.
