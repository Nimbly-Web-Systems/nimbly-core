# Nimbly

Nimbly is a lightweight framework and admin platform for building custom web applications — fast, without overhead.

It is designed for developers and frontenders who want to build real systems without fighting a framework. No heavy abstractions, no bloated dependencies, no separation between prototype and production. What you build from day one becomes the final system.

---

## What you get

- **Routing** — clean URL routing, file-based
- **Template engine** — shortcode-based, output-format agnostic
- **Built-in admin** — inline editing, resource management, media library
- **Data layer** — file-based JSON records, no database required
- **API** — every resource is automatically available via REST
- **User management** — roles, access control, sessions
- **Multi-language** — i18n built in, no extra configuration

## How it works

Nimbly separates **core** (the framework, stable, never modified) from **ext** (your application — routes, templates, data, logic).

Development happens primarily in:
- HTML + Tailwind CSS + Alpine.js for the frontend
- Shortcodes for data, logic, and composition in templates
- JSON `.meta` files to define data structures

Backend work is minimal by design. Frontenders can build complete features. Teams move fast without needing deep framework knowledge.

## Why Nimbly

Most web projects don't need the complexity that is typically introduced from the start. Nimbly removes that complexity without removing capability.

Systems built with Nimbly are fast to start, easy to maintain, and straightforward to hand over. There is no hidden magic — what you see is what runs.

---

For precise implementation instructions, shortcode reference, and conventions for AI-assisted development, see [Nimbly.md](Nimbly.md).
