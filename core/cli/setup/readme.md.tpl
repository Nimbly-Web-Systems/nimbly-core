Getting started
===============

**Requirements**

- Node 20+
- PHP 8+ or Docker

**Clone both repos**

```bash
git clone %%CORE_REPO%% %%SITE_NAME%%
git clone %%EXT_REPO%% %%SITE_NAME%%/ext
cd %%SITE_NAME%%
```

**First-time setup**

```bash
./nimbly init   # installs dependencies, creates .htaccess/.user.ini/data dirs/admin user, and builds assets
```

If PHP is not available on the host, `./nimbly` runs the CLI through Docker automatically. To force Docker:

```bash
./nimbly --docker init
```

Browse to [http://localhost](http://localhost).

Docker and VS Code dev containers are optional local-development conveniences when this project includes that setup.

Day-to-day
----------

```bash
./nimbly build   # full rebuild
npm run up      # restart the container
```
