# Metadatapp

Metadatapp is an open-source metadata management platform for biomedical and laboratory research workflows. It combines a Symfony/API Platform backend, the Osoma React frontend, and server-side integrations with external laboratory systems.

The project is being opened by Neuronautix so the community can inspect, run, extend, and preserve the work even if internal development slows down.

## Project Status

Metadatapp is an open-source project developed and maintained by [Neuronautix](https://www.neuronautix.com).

The project was initially explored as a potential startup initiative. The planned incorporation path is no longer moving forward, but the technical and scientific mission continues as an open-source effort, now driven by Damien Huzard (Neuronautix) with the help of an AI-agent toolchain.

Metadatapp aims to support FAIR-by-design metadata management for biomedical and preclinical research, with a focus on structured experimental descriptions, interoperability, ontology-based modelling, JSON-LD, RO-Crate, and AI-ready research data workflows.

Public-preview caveats — before production use, review:

- authentication and tenant isolation for your deployment model
- Connected Apps credential storage and external API contracts
- generated fixtures and sample data
- deployment configuration

## License

Metadatapp is licensed under the GNU Affero General Public License v3.0 or later. See [LICENSE](LICENSE).

SPDX identifier: `AGPL-3.0-or-later`

Copyright (C) 2026 Neuronautix. See [NOTICE](NOTICE).

The Metadatapp name and brand are owned by Neuronautix. Use of the source code under AGPLv3 does not grant permission to use the Metadatapp name, logo, or associated branding in a way that suggests official endorsement or affiliation.

## Repository Layout

```text
metadatapp/
├── api/                    Symfony + API Platform backend
├── osoma/                  Vite + React frontend
├── e2e/                    Playwright tests against live services
├── infrastructure/docker/  Docker Compose stack definitions
├── docs/guide/             User & Integration Guide (Sphinx + MyST)
├── doc/                    Project documentation and wiki
├── reports/                Investigation notes and ad hoc reports
├── prompts/                Prompt assets for AI-assisted workflows
└── .github/                CI, issue templates, and agent adapters
```

## Requirements

- Docker and Docker Compose (with the daemon running)
- Git
- Castor task runner (the [Local Setup](#local-setup) steps install it)
- pnpm for direct frontend work
- PHP 8.4 and Composer for direct backend work outside Docker

> Prefer a ready-made environment? The Dev Container (`.devcontainer/`, usable with
> VS Code Dev Containers or GitHub Codespaces) provisions Docker, a pinned Castor
> release, pnpm, PHP, and Composer for you. Open the repository in a container and
> skip straight to step 2 below.

## Local Setup

1. **Install Castor.** Castor is the task runner that drives the Docker stack and
   every QA command. Install the latest release and make sure the install
   directory (`~/.local/bin`) is on your `PATH`:

   ```bash
   curl "https://castor.jolicode.com/install" | bash
   castor --version
   ```

   See [jolicode/castor](https://github.com/jolicode/castor) for alternative
   installation methods (PHAR, Composer, Homebrew).

2. **Add local development hostnames** to `/etc/hosts`:

   ```bash
   echo "127.0.0.1 metadatapp.test osoma.metadatapp.test auth.metadatapp.test" | sudo tee -a /etc/hosts
   ```

3. **Build and start the full stack.** The first run builds the Docker images and
   installs the backend and frontend dependencies, so it can take several minutes:

   ```bash
   castor start
   ```

4. **Load demo data** so the instance has sample content to explore:

   ```bash
   castor fixture
   ```

5. **Verify the stack is up.** `castor about` prints the project URLs and
   `castor ps` shows container status:

   ```bash
   castor about
   castor ps
   ```

Common URLs:

- API documentation: <https://metadatapp.test/docs>
- Symfony profiler: <https://metadatapp.test/_profiler>
- Osoma frontend: <https://osoma.metadatapp.test>
- Keycloak: <https://auth.metadatapp.test/oidc/>

The local stack uses development-only credentials and self-signed certificates. Do not reuse local secrets in production.

## Configuration and Environments

Metadatapp runs in three distinct environments. They differ mainly in **hostnames** and in the **Keycloak realm** that issues tokens, so a value that is correct for one environment will break authentication in another. Configure each one explicitly rather than assuming the defaults carry over.

| Environment | Used for | Hostnames | Keycloak realm | Config source |
| --- | --- | --- | --- | --- |
| Local development | `castor start` on your machine | `*.metadatapp.test` (self-signed) | `demo` | `castor` variables + `infrastructure/docker/docker-compose.yml`, overridable in `api/.env.local` and `osoma/.env.local` |
| Automated tests | PHPUnit / Playwright | `*.metadatapp.test` | `demo` | `api/.env.test`, `e2e/.env.example` |
| Self-hosted deployment | Your own server | the domain you choose (e.g. `metadatapp.net`) | the realm you create (e.g. `users`) | deployment secrets / your orchestrator's environment |

### Where configuration lives

- **API (`api/`)** — runtime environment variables. Copy [`api/.env.example`](api/.env.example) to `api/.env.local` for local overrides; in deployment supply the same variables as secrets. See [api/README.md](api/README.md).
- **Osoma (`osoma/`)** — `VITE_*` variables baked in **at build time**. Copy [`osoma/.env.example`](osoma/.env.example) to `osoma/.env.local`. Changing these requires a rebuild of the frontend, not just a restart. See [osoma/README.md](osoma/README.md).
- **Docker stack (`infrastructure/docker/`)** — hostnames are derived from `PROJECT_ROOT_DOMAIN`, which `castor` sets from the `root_domain` variable (default `metadatapp.test`, defined in [castor.php](castor.php)). The compose file expands it into `auth.<domain>`, `osoma.<domain>`, and the API host.

### Identity settings must agree across all three components

The most common setup failure is an OIDC mismatch: the Keycloak realm, the Osoma client, and the API validator must all point at the **same realm, issuer, and audience**. If they disagree, login appears to succeed in the browser but every API call returns `401 Unauthorized`.

| Logical setting | API variable | Osoma variable | Must equal |
| --- | --- | --- | --- |
| Issuer (browser-facing) | `OIDC_SERVER_URL` | `VITE_OIDC_SERVER_URL` | the realm's `issuer` from `https://<auth-host>/realms/<realm>/.well-known/openid-configuration` |
| Issuer (server-to-server) | `OIDC_SERVER_URL_INTERNAL` | — | same realm, reachable from inside the API container |
| OIDC client | — (validated via audience) | `VITE_OIDC_CLIENT_ID` | the Keycloak client id (e.g. `osoma`) |
| Token audience | `OIDC_AUD` | — | the `aud` claim the access token actually carries (add an *Audience* mapper to the client if needed) |

> **Note on realm and path.** The repository defaults use the `demo` realm, but a real deployment typically uses its own realm name (the public instance uses `users`). The example files also include an `/oidc` path segment (`.../oidc/realms/demo`) that the Docker stack does not; always use the exact `issuer` value returned by the realm's `.well-known/openid-configuration` endpoint. When you rename the domain or realm, update **both** the API and Osoma variables together.

### Troubleshooting authentication

If login redirects work but API requests return `401`:

1. In the browser, decode the access token's `iss` and `aud` (`JSON.parse(atob(token.split('.')[1]))`).
2. Confirm `iss` matches the API's `OIDC_SERVER_URL` exactly, and that this realm is reachable (`curl https://<auth-host>/realms/<realm>/.well-known/openid-configuration` should return `200`, not `404`).
3. Confirm `aud` matches the API's `OIDC_AUD`.
4. After changing API variables, restart the API and clear its cache so the cached OIDC discovery document is refreshed.

## Development

Backend checks:

```bash
castor phpunit
castor qa:phpstan
castor qa:cs --dry-run
castor schema-validate
```

Frontend checks:

```bash
castor qa:osoma:build

cd osoma
pnpm build
pnpm run test:integration
```

E2E checks:

```bash
cd e2e
npm test
```

The E2E package currently uses npm because it has its own lockfile; the rest of
the workspace uses pnpm.

Agent documentation lint:

```bash
castor qa:agent-docs
```

## Integrations

Connected Apps integrations are implemented server-side under `api/src/ConnectedApps/`. The frontend must not call external laboratory systems directly.

Default local configuration uses mock or placeholder endpoints where possible. Real integration credentials must be supplied through untracked local environment files or deployment secrets.

Users can enter per-integration API keys, access tokens, or login credentials from Osoma's Connected Applications settings. The API stores those values server-side and returns only masked hints. See [docs/CREDENTIALS.md](docs/CREDENTIALS.md).

The AI assistant is disabled by default. Current public-preview providers are `null`, `mock`, `curate_gpt`, `openai`, and `anthropic`. OpenAI and Anthropic keys can be entered from Osoma's AI Providers settings or supplied as deployment secrets.

## Documentation

📖 Published guide: **<https://neuronautix.github.io/metadatapp/>** (hosted on Read the Docs once
the project is imported — see [docs/guide/for-developers/publishing-docs.md](docs/guide/for-developers/publishing-docs.md)).

The **User & Integration Guide** ([`docs/guide/`](docs/guide/)) is the complete
manual for everyone who interacts with an instance, coding or non-coding. It
covers the concepts and domain model, a screen-by-screen tour of the Osoma
frontend (research records, importing/curation, FAIR and exports, the AI
assistant), step-by-step instructions for connecting each third-party app
(SoftMouse, eLabFTW, FAIR3R, OSF, Tecniplast DVC, CEDAR, JAX Phenome, and more),
the metadata models & forms section (CDEs, Canonical Forms, and the HCM Minimal
Metadata Form worked example), administration, self-hosting and configuration, a
developer/extension guide, and a glossary and FAQ.

It is a Sphinx site authored in Markdown (MyST). Build it locally with:

```bash
pip install -r docs/guide/requirements.txt
sphinx-build -W -b html docs/guide docs/guide/_build/html
# open docs/guide/_build/html/index.html
```

The Connected Apps pages are kept in sync with the `AppCode` enum by a check that
runs in CI (`.github/workflows/docs.yml`) and locally:

```bash
castor qa:docs-check            # or: python3 scripts/check_connected_apps_docs.py
```

If you add a new integration, add a page under `docs/guide/connecting-apps/`
(use [`elabftw.md`](docs/guide/connecting-apps/elabftw.md) as the template) or the
check will fail.

## Contributing

Community contributions are welcome while the project is in public preview. Start with:

- [CONTRIBUTING.md](CONTRIBUTING.md)
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
- [SECURITY.md](SECURITY.md)
- [SUPPORT.md](SUPPORT.md)
- [GOVERNANCE.md](GOVERNANCE.md)

## Maintainers

Metadatapp is maintained in the Neuronautix GitHub organization. See [GOVERNANCE.md](GOVERNANCE.md) for the public governance model.

Beyond this open-source codebase, Neuronautix continues to deliver metadata management and FAIR data services to research groups, and develops applications that support better scientific outputs. More at [www.neuronautix.com](https://www.neuronautix.com).

## Acknowledgements

Metadatapp would not exist in its current form without **Laurent Huzard**, who built the original backend and patiently taught me — Damien Huzard — enough of his craft that I could pick up the codebase and carry it forward. The architecture you see today is, in large part, his.

Warm thanks also go to **Frédéric Deverre** for the strategic conversations during the startup exploration phase — the framing of what Metadatapp should and should not try to be owes a great deal to those discussions.

Continued development is led by Damien Huzard with assistance from a small team of AI coding agents.
