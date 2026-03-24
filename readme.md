# Nimbly — System Overview

Nimbly is a lightweight, modular framework and admin platform for building custom web applications.

It is designed to deliver structured, maintainable systems fast — without the complexity and overhead of traditional frameworks or CMS platforms.

---

## 1. What Nimbly Is

Nimbly is a system builder.

It enables developers and frontenders to create:
- websites
- platforms
- dashboards
- internal tools
- data-driven applications

It is designed to model real-world systems through structured data.

---

## 2. Core Architecture

Nimbly separates concerns into two layers:

### CORE
CORE provides a set of building blocks used to construct fully custom applications:

- routing  
- template engine (shortcodes)  
- admin system  
- built-in REST-like API for all resources  
- user management  
- system services  

The core remains stable and is not modified during projects.

The core also exposes all resources through a built-in REST-like API.

This allows systems to be used in a traditional templated approach, as well as in API-driven or headless setups, without additional configuration.

---

### EXT
EXT is where the application takes shape:

- routes  
- templates  
- data structures  
- custom logic  
- optional modules  

EXT defines the application, while CORE provides the foundation it runs on.

---

## 3. Development Model

In practice, most development in Nimbly happens in:

- HTML  
- Tailwind CSS  
- Alpine.js  
- templates and reusable components  

Backend work is minimal and decreases over time as reusable building blocks grow.

This allows:
- frontenders to take on a large part of the work  
- systems to be built without heavy backend development  
- teams to move quickly without deep framework knowledge  

Nimbly enables a fast start without requiring a change in approach later.

What is built from the start becomes part of the final system.  
Templates, data structures, and behavior evolve together into a complete system.

---

## 4. Data Storage

Nimbly includes an integrated data layer designed for clarity, control, and speed.

Data is organized into structured resources, each defining its own fields and behavior.  
This keeps data explicit and consistent across the system.

Records are stored individually in a transparent format, making them directly accessible for inspection, debugging, and manipulation.

Because data lives alongside the codebase, it is versioned, traceable, and deployed as part of the application itself.  
Data and structure evolve together, avoiding drift between environments and eliminating the need for separate synchronization layers.

This approach keeps systems lightweight while still handling real-world applications efficiently.  
For most use cases, data can be processed directly in memory with excellent performance, keeping development fast and overhead low.

When a project requires integration with other systems or data sources, Nimbly can be extended without changing the overall development approach.

The result is a data layer that remains simple by default, without limiting how systems can evolve.

---

## 5. Templates & Rendering

Nimbly includes a built-in template system as part of the core.

Templates are written in a target output format (most commonly HTML) and extended with shortcodes.  
These shortcodes provide access to data, logic, and reusable structures directly within the output.

Shortcodes are not limited to HTML and can be used in any format, including CSS, JavaScript, JSON, or XML.  
This allows the same system to generate different types of output using a consistent approach.

Shortcodes can also reference other templates, enabling composition and reuse.  
This allows complex structures to be built from smaller, consistent building blocks.

There are no additional templating layers or complex abstractions.  
The system deliberately keeps the syntax minimal, so templates remain close to their final output, regardless of format.

The core also includes Tailwind CSS and Alpine.js, both of which operate directly within HTML.  
This allows structure, styling, and behavior to be defined in a single place, without switching between different languages or layers.

This approach keeps development focused:
- output format as the working layer  
- shortcodes for data, logic, and composition  
- Tailwind and Alpine for styling and interaction (when working in HTML)  

By keeping everything close to the final output, Nimbly reduces fragmentation while still supporting fully custom applications.

---

## 6. Admin Experience

Nimbly includes a built-in admin environment for managing and operating applications.

### Inline editing
- edit directly on the page  
- no context switching  
- controlled by template fields  

### Resource management
- structured data editing (CRUD)  
- works across any data type  

### Media library
- drag & drop upload  
- automatic optimization  
- duplicate detection  
- external media support  

### System dashboard
- insight into system usage and data  

The system is designed to work without requiring dedicated training or complex workflows.

---

## 7. Key Principles

- structure over abstraction  
- simplicity over complexity  
- frontend-first development  
- minimal backend by default  
- reuse through composition  
- stable core, flexible extensions  

---

## 8. Why Nimbly

Nimbly is designed around a simple idea: most applications do not need the complexity that is typically introduced from the start.

By keeping the core stable and the development model straightforward, systems can grow naturally without forcing early architectural decisions upfront.

There is no separation between starting and finishing.  
What is built from the start becomes part of the final system.

Nimbly is built around three core qualities:

### Speed

Development starts immediately, without setup or architectural overhead.

What begins as a quick iteration becomes part of the final system.  
Templates, data structures, and behavior evolve together without requiring a rewrite.

---

### Performance

Because unnecessary layers are removed, execution remains minimal.

Pages are rendered without heavy abstraction, large dependency chains, or excessive database interaction.  
The result is predictable, fast response times with very low overhead.

---

### Flexibility

Nimbly does not impose a fixed way of building applications.

Core behavior can be extended or replaced where needed, and different approaches can be combined within the same system.

This allows:
- integration with external data sources or databases when required  
- extension or replacement of core functionality  
- alternative rendering approaches, including API-driven frontends  

---

With Nimbly, you can build real-world systems fast, efficiently, and in full control — without unnecessary complexity or external dependencies.

---