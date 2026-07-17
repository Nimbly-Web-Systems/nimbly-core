# Nimbly

Nimbly is a full-stack design system for custom web products. It brings
interface patterns, real data, routing and application logic into one atomic
architecture.

The routes, templates and resource definitions used in the first working
prototype become the production system. Nimbly stays fast, lean and flexible
as the product grows.

## One resource definition drives the stack

Describe a resource once in a `.meta` file. Its fields drive file-backed
persistence, validation, indexes, admin forms and REST API behavior. The same
resource connects to permissions, public forms, lifecycle events, background
jobs and inline editing through built-in Nimbly capabilities.

Templates work with that data directly:

```html
[#data articles filter=published:yes sort=date|desc#]
[#repeat data.articles tpl=card-article#]
```

Shortcodes load and format data, enforce access, render responsive images and
compose templates. Reusable templates turn those operations into interface
patterns. Modules add complete features with their own routes, resources and
application logic.

## Dynamic pages in milliseconds

Dynamic routing, resource reads, permissions and template rendering commonly
complete in tens of milliseconds on real Nimbly sites. The request path opens
no database connection, hydrates no ORM, builds no dependency container and
crosses no large middleware pipeline.

The result is a full application stack with very little framework and tooling
overhead.

## Every route controls its own output

Each URL is an independent endpoint. A route can render a Nimbly page, return
HTML or JSON, or power a dedicated frontend. Static and dynamic routes live
beside the templates and logic that implement them.

The reusable design system lives in `core/`. Each application owns its routes,
data model, templates, components, business logic, configuration and visual
identity in a separate `ext/` repository. Core and each application evolve
independently while every product remains fully custom.

## Try Nimbly

```bash
git clone git@github.com:Nimbly-Web-Systems/nimbly-core.git my-project
cd my-project
./nimbly init
```

`./nimbly init` installs the dependencies, prepares the application, creates
the first admin user and builds the assets.

Nimbly requires Node 20+ and either PHP 8+ or Docker.

## A strong fit for custom web products

Nimbly is built for websites, digital platforms, portals, membership systems,
internal tools and operational web apps that need custom interfaces, structured
data and real workflows. This is Nimbly at its best: a fast runtime, a lean
codebase and the freedom to shape every route around the product.

Read the complete [Nimbly implementation reference](NIMBLY.md).
