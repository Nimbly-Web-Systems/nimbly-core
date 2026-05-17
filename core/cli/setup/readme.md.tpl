Getting started
===============

**Requirements**

- [Docker](https://docs.docker.com/get-docker/)
- [VS Code](https://code.visualstudio.com/) with the [Dev Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers)

**Clone both repos**

```bash
git clone %%CORE_REPO%% %%SITE_NAME%%
git clone %%EXT_REPO%% %%SITE_NAME%%/ext
cd %%SITE_NAME%%
```

**Open in VS Code**

```bash
code .
```

VS Code will detect the dev container and ask to reopen inside it. Accept — this builds the Docker image and installs Node dependencies automatically.

**First-time setup**

In the VS Code terminal (inside the container):

```bash
npm run nimbly -- setup   # creates .htaccess, data dirs and admin user — runs once
npm run build   # compiles CSS and JS
```

Browse to [http://localhost](http://localhost).

Day-to-day
----------

```bash
npm run build   # full rebuild
npm run up      # restart the container
```
