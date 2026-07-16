# Website

This website is built using [Docusaurus](https://docusaurus.io/), a modern static website generator.

## Installation

```bash
npm install
```

**Note**: feel free to use the package manager of your choice.

## Local Development

```bash
npm run start
```

This command starts a local development server and opens up a browser window. Most changes are reflected live without having to restart the server.

## Build

There are two build targets, because this site is consumed two different ways — public hosting and embedding inside the running LazyMePHP app — and each needs a different `baseUrl` baked into the compiled HTML/JS.

```bash
npm run build
```

Default target: public hosting as a **GitHub Pages project site**, served at `https://peixinho.github.io/LazyMePHP/`. Generates static content into the `build` directory, servable by any static host (GitHub Pages, Netlify, `npx serve build`, etc.) as long as it's mounted at the `/LazyMePHP/` path the build expects.

```bash
DOCS_BASE_URL=/docs/ npm run build
```

Embedded target: rebuilds `build/` for `Core\Http\Kernel`/`Core\DocsServer`, which serve this directory from inside the running LazyMePHP app under `/docs`. **This is the version committed to `docs/build/` in the main repo** — always rebuild with this exact override before committing, otherwise the in-app docs viewer breaks (its asset paths won't match the `/docs` mount point).

Override `DOCS_URL`/`DOCS_BASE_URL` for any other target (a custom domain, a docs subdomain, a different path) without touching `docusaurus.config.js`.

## Deployment

Using SSH:

```bash
USE_SSH=true npm run deploy
```

Not using SSH:

```bash
GIT_USER=<Your GitHub username> npm run deploy
```

This builds with the default (public-hosting) target and pushes to the `gh-pages` branch, which GitHub Pages serves at `https://peixinho.github.io/LazyMePHP/`.
