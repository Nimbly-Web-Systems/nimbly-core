# Nimbly

Nimbly is a fast, lean and flexible full-stack design system for custom
websites, web applications and progressive web apps.

Reusable interface patterns connect directly to real data, routing and
application logic.

## From prototype to production

Nimbly starts with the part people use. Create a route, compose a page from
small templates and components, then connect structured data and application
logic as the product grows. The working prototype becomes the production
application in the same architecture.

## Why Nimbly

**Fast.** A small PHP runtime and direct resource model keep dynamic pages
responsive.

**Lean.** File backed resources, focused libraries and simple deployment keep
the system easy to understand and operate.

**Flexible.** Every route is an independent endpoint. It can render a Nimbly
template, serve HTML or JSON, or power a dedicated frontend.

**Full stack.** Routing, templates, structured data, REST APIs, admin,
authentication, permissions, forms, media, inline editing, internationalization,
jobs and deployment workflows are ready to work together.

Reusable framework capabilities live in `core/`. Every application owns its
routes, data model, templates, components, business logic, configuration and
visual identity in a separate `ext/` repository.

## Try Nimbly

```bash
git clone git@github.com:Nimbly-Web-Systems/nimbly-core.git my-project
cd my-project
./nimbly init
```

`./nimbly init` installs the dependencies, prepares the application, creates
the first admin user and builds the assets.

Nimbly requires Node 20+ and either PHP 8+ or Docker.

## A good fit

Nimbly works especially well for custom sites, digital platforms, portals,
membership systems, internal tools and operational web apps. It gives each
project a fast foundation, a lean architecture and room to become exactly what
its users need.

Read the complete [Nimbly implementation reference](NIMBLY.md).
