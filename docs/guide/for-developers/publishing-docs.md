# Building & publishing this guide

This guide is a [Sphinx](https://www.sphinx-doc.org/) site authored in Markdown
(via [MyST](https://myst-parser.readthedocs.io/)). Sources live in `docs/guide/`.

## Build locally

```bash
pip install -r docs/guide/requirements.txt
sphinx-build -W -b html docs/guide docs/guide/_build/html
# then open docs/guide/_build/html/index.html
```

The `-W` flag turns warnings (including broken cross-references) into errors, which
matches CI. Add any new page to the nearest `toctree` so it is reachable.

## CI

`.github/workflows/docs.yml` builds the site with `-W` and runs
`scripts/check_connected_apps_docs.py` (the Connected Apps coverage gate) on every
change to `docs/guide/**`, the spec, the script, or the `AppCode` enum, and deploys
to GitHub Pages on `main` (best-effort — see [Hosting](#hosting)).

## On the open-source mirror

The public mirror (`Neuronautix/metadatapp-oss`) receives a clean snapshot from the
private repository. The docs are designed to keep working there:

- The publication filter keeps everything the docs need — `docs/guide/`,
  `docs.yml`, `.readthedocs.yaml`, `scripts/check_connected_apps_docs.py`,
  `osoma/resources/metadatapp.json` (the API spec), and `api/src/Enum/AppCode.php`.
- All in-guide links to repository files point at the **public mirror**, so they
  resolve for public readers.
- Because the mirror is **public**, GitHub Pages is available for free there.
- Every snapshot push triggers `docs.yml` on the mirror, which **rebuilds the guide
  with `-W` and re-runs the coverage check** — so a broken docs build shows up on
  the mirror itself, not just privately.

```{tip}
If the publication filter (`oss/do-not-merge-publication-filter`) is ever changed,
make sure it does **not** delete `docs/guide/`, `docs.yml`, `.readthedocs.yaml`,
`scripts/check_connected_apps_docs.py`, `osoma/resources/metadatapp.json`, or
`api/src/Enum/AppCode.php`.
```

## Hosting

Two paths are wired up. They are complementary — you can run either, or both.

### GitHub Pages (automated, zero external accounts)

`docs.yml` builds the guide on every change and **deploys it to GitHub Pages on
each push to `main`** (pull requests build and validate but do not deploy). The
only one-time step is in-repo:

1. **Settings → Pages → Build and deployment → Source: GitHub Actions.**

After that, every merge republishes automatically to
`https://neuronautix.github.io/metadatapp-oss/`. No external signup, no secrets.

### Read the Docs (versioning, PR previews, search)

The repository also ships an `.readthedocs.yaml`, so RTD is a one-time import:

1. Sign in at [readthedocs.org](https://readthedocs.org/) and **Import** the
   `Neuronautix/metadatapp-oss` repository. RTD reads `.readthedocs.yaml` automatically
   (Python 3.12, `docs/guide/conf.py`, `fail_on_warning: true`).
2. The first build publishes to `https://<project-slug>.readthedocs.io`, with
   versioned docs, per-pull-request build previews, and hosted search.

### Custom domain (`doc.metadatapp.net`)

A domain points at **one** host — choose your canonical one:

- **On Read the Docs:** **Admin → Domains → Add Domain** → `doc.metadatapp.net`
  (mark canonical; RTD provisions HTTPS), then add DNS
  `doc.metadatapp.net CNAME <project-slug>.readthedocs.io`.
- **On GitHub Pages:** **Settings → Pages → Custom domain** → `doc.metadatapp.net`
  (this writes a `CNAME` file), then add DNS
  `doc.metadatapp.net CNAME neuronautix.github.io`.

```{tip}
Recommended setup: use **GitHub Pages** for the always-on, fully-automated build,
and add **Read the Docs** when you want versioned releases and PR previews. Make
whichever one you prefer the canonical home for `doc.metadatapp.net`.
```

### Point the in-app link at your chosen host

Osoma's **Settings → Documentation** page links to the `VITE_DOCS_URL` build
variable (default `https://doc.metadatapp.net`). Until your custom domain is live,
set it to the active host — e.g. `https://neuronautix.github.io/metadatapp-oss` — and
rebuild Osoma. See
[Configuration](../self-hosting/configuration.md#osoma-variables-vite_-build-time).
