# Working with %%SITE_NAME%%

This repository is the application layer of a Nimbly project. Nimbly is a
full-stack, atomic design system in which routes, data, templates and
application logic grow together from prototype to production.

Application code and configuration live in `ext/`. The reusable Nimbly design
system lives in the parent project checkout. Run all `./nimbly` commands from
that parent directory.

## Requirements

- Node 20+
- PHP 8+ or Docker
- Git

## Clone and initialize

```bash
git clone %%CORE_REPO%% %%SITE_NAME%%
git clone %%EXT_REPO%% %%SITE_NAME%%/ext
cd %%SITE_NAME%%
./nimbly init
```

`./nimbly init` installs dependencies, prepares the application, creates the
first admin user and builds the assets. It uses host PHP when available and
falls back to Docker automatically. Use `./nimbly --docker init` to force the
Docker path.

Start the local site and open [http://localhost](http://localhost):

```bash
./nimbly up
```

The admin is available at
[http://localhost/nb-admin/](http://localhost/nb-admin/).

## Daily development

Run these commands from the parent project directory:

```bash
./nimbly up         # Start or restart the local Docker environment
./nimbly watch      # Build once, then rebuild assets as source files change
./nimbly build      # Run one complete asset build
./nimbly test:run   # Run the end-to-end test suite
./nimbly help       # List every available command
```

Run `./nimbly watch` while changing templates, CSS, JavaScript or translation
files. A one-time `./nimbly build` is enough for changes that do not need a
continuous watcher.

## Where project work lives

- `ext/uri/` contains routes and route-scoped templates.
- `ext/tpl/` contains reusable interface templates.
- `ext/data/` contains resource definitions and application data.
- `ext/lib/` contains project libraries and shortcode logic.
- `ext/modules/` contains self-contained application features.
- `ext/theme.css` and `ext/tailwind.theme.js` define the project theme.

The parent directory and `ext/` are separate Git repositories:

```bash
git status          # Nimbly core repository
git -C ext status   # Application repository
```

Application changes belong in the `ext/` repository. Framework changes belong
in core only when every Nimbly application needs them.

## Documentation

Read the complete
[Nimbly implementation reference](https://github.com/Nimbly-Web-Systems/nimbly-core/blob/master/NIMBLY.md)
for architecture, conventions and all commands.
