# Nimbly

Nimbly is a full-stack atomic design system for building custom web applications
and progressive web apps fast. It combines a lightweight runtime with routing,
data, APIs, admin, templates, authentication, interface patterns, and
development workflows as small, reliable building blocks.

Atomic design in Nimbly covers the complete application stack. Pages are
composed from focused templates, shortcodes, and components. Structured
resources connect directly to forms, APIs, administration, permissions, and
editable content. The same clear contracts also give developers and coding
agents precise, navigable units to work with.

Every route is an independent endpoint with control over its own output and
frontend architecture. One route can render a Nimbly template, another can
return JSON, and another can run React, vanilla JavaScript, plain HTML, or the
tools best suited to that part of the application.

The reusable system lives in `core/`. Each application owns its routes, content
model, templates, business logic, configuration, and identity in a separate
`ext/` repository. A working prototype can grow in the same structure into a
complete production application.

## What Nimbly provides

- Atomic templates, shortcodes, and components for composing application behavior and pages
- Independent static and dynamic routes that can render templates, serve HTML or JSON, or power any frontend stack
- JSON resources with schemas, validation, indexes, lifecycle events, and REST API access
- Built-in authentication, users, roles, permissions, admin, media, forms, jobs, and inline editing
- Opt-in manifests, app icons, and safe service-worker foundations for installable PWAs
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

## Documentation

See [NIMBLY.md](NIMBLY.md) for the full implementation reference.
