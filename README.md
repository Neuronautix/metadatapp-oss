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
- known limitations in [.github/KNOWN_LIMITATIONS.md](.github/KNOWN_LIMITATIONS.md)

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
├── doc/                    Project documentation and wiki
├── reports/                Investigation notes and ad hoc reports
├── prompts/                Prompt assets for AI-assisted workflows
└── .github/                CI, issue templates, and agent adapters
```

## Requirements

- Docker and Docker Compose
- Git
- Castor task runner
- pnpm for direct frontend work
- PHP 8.4 and Composer for direct backend work outside Docker

## Local Setup

Add local development hostnames to `/etc/hosts`:

```bash
echo "127.0.0.1 metadatapp.test osoma.metadatapp.test auth.metadatapp.test" | sudo tee -a /etc/hosts
```

Start the full stack:

```bash
castor start
castor fixture
```

Common URLs:

- API documentation: <https://metadatapp.test/docs>
- Symfony profiler: <https://metadatapp.test/_profiler>
- Osoma frontend: <https://osoma.metadatapp.test>
- Keycloak: <https://auth.metadatapp.test/oidc/>

The local stack uses development-only credentials and self-signed certificates. Do not reuse local secrets in production.

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
