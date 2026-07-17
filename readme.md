# Nimbly

Nimbly is a full-stack design system for building custom web applications. It
combines a lightweight PHP runtime with routing, structured resources, a
template engine, authentication, an API, an admin interface, and a modern
frontend toolchain.

Nimbly keeps the reusable system in `core/` and each application's routes,
content model, templates, business logic, and visual identity in its own
`ext/` repository. This gives applications shared full-stack conventions
without forcing them into a fixed page design or generic component catalogue.

## What Nimbly provides

- File-based routing and a composable shortcode template system
- JSON resources with metadata, validation, indexes, lifecycle events, and API access
- Authentication, roles, permissions, administration, media, forms, and inline editing
- Tailwind CSS, DaisyUI, and Alpine.js building blocks for custom interfaces
- CLI workflows for setup, builds, migrations, testing, scheduling, and deployment

## Getting started

```bash
git clone git@github.com:Nimbly-Web-Systems/nimbly-core.git my-project
cd my-project
./nimbly init
```

`./nimbly init` installs dependencies, prepares `ext/`, creates the first admin
user, and builds assets.

**Requirements:** Node 20+ and either PHP 8+ or Docker.

## Deployment

Nimbly supports both container and manual VPS deployment. Run
`./nimbly docker:init` to generate an application Dockerfile and GitHub Actions
workflow in `ext/`. The workflow publishes an app image that can run on a VPS,
EC2 instance, Render, or any container host.

Manual VPS deployment is equally valid for self-managed installs with existing
Apache/PHP infrastructure. See `NIMBLY.md` for cron, env, and update details.

## Stack

PHP 8 · Tailwind CSS 4 · DaisyUI 5 · Alpine.js · esbuild

## Documentation

See [NIMBLY.md](NIMBLY.md) for the full implementation reference.
