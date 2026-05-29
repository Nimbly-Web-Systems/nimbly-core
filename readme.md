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

**Requirements:** Node 20+ and Docker.

## Deployment

Nimbly's public deployment path is Docker-first. Run `./nimbly docker:init` to
generate an application Dockerfile and GitHub Actions workflow in `ext/`. The
workflow publishes an app image that can run on a VPS, EC2 instance, Render, or
any container host.

Manual VPS deployment is also supported for self-managed installs with existing
Apache/PHP infrastructure. See `NIMBLY.md` for cron, env, and update details.

## Stack

PHP 8 · Tailwind CSS 4 · DaisyUI 5 · Alpine.js · esbuild

## Documentation

See [NIMBLY.md](NIMBLY.md) for the full implementation reference.
