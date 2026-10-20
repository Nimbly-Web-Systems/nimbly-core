# Nimbly

Nimbly is a full-stack atomic design system for custom digital products.
Resources, libraries, templates and routes are small building blocks that
combine into features, and every route is a blank canvas.

## Why Nimbly

- **Full-stack atomic composition.** Data, logic and presentation are small
  building blocks, as granular as the project needs.
- **Every route is a blank canvas.** The standard page shell, your own HTML,
  JSON, XML, text, files or PDF.
- **Any frontend.** Tailwind CSS, DaisyUI and Alpine.js by default; React, Vue
  or any other toolset where you choose.
- **Data defined once.** A resource definition gives the admin, forms, REST
  API, permissions and history.
- **Lean by design.** A request loads only the building blocks its route uses;
  code, content and data are plain files.
- **Integrated platform.** Routing, templates, data, users and roles, media,
  jobs, scheduling, email and agents.
- **AI-assisted development.** Focused files and searchable documentation for
  coding agents.

## Try Nimbly

```bash
git clone https://github.com/Nimbly-Web-Systems/nimbly-core.git my-project
cd my-project
./nimbly init
```

`./nimbly init` installs the dependencies, prepares the application, creates
the first admin user and builds the assets.

Start the site and open [http://localhost](http://localhost):

```bash
./nimbly up
```

A new site opens on a short home page that shows where pages, content and
data live. The admin is at
[http://localhost/nb-admin/](http://localhost/nb-admin/); log in with the
admin user you just created.

Nimbly requires Git, Node.js 20 or later, and PHP 8 or Docker. See
[Installation](https://atomic.nimblyweb.com/en/docs/learn/installation) for
the details.

## Documentation

The documentation is at
[atomic.nimblyweb.com](https://atomic.nimblyweb.com). Coding agents use the
complete reference in [NIMBLY.md](NIMBLY.md), which every project has at its
root.

---

Nimblify your next project. [nimblyweb.com](https://nimblyweb.com)
