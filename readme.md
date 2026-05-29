# Nimbly

Nimbly is a lean web framework that gives you the building blocks to create exactly what you need, fast.

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
