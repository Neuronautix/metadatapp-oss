# Self-hosting & configuration

This section is for **operators** who run their own Metadatapp instance. It covers
installation, the full configuration reference, identity setup, and security.

```{toctree}
:maxdepth: 1

configuration
identity
security
```

## Runtime components

A Metadatapp deployment is a small set of services (see the
[architecture overview](../for-developers/index)):

- **API** (Symfony / API Platform, served by FrankenPHP) — owns all data and all
  external calls.
- **Osoma** (React/Vite static build) — the frontend.
- **PostgreSQL** — the database.
- **Keycloak** — OIDC identity provider.
- **Traefik** — routes the hostnames (HTTPS).
- **Mercure** — real-time updates.

The whole stack is defined as Docker Compose under `infrastructure/docker/` and
driven by the Castor task runner.

## Installing

### Local / evaluation

Follow [Local Setup in the repository README](https://github.com/Neuronautix/metadatapp-oss/blob/main/README.md#local-setup):
install Castor, add the `*.metadatapp.test` hostnames, run `castor start`, then
`castor fixture`. This brings up the full stack with self-signed certificates and a
demo realm. See also [Getting started](../getting-started).

### Your own server

There is no single prescribed production topology — the stack is standard Docker
Compose, so you can run it on any Docker host. At minimum you must:

1. Choose your **domain** and point DNS at your host.
2. Provide **real TLS certificates** (replace the self-signed local ones).
3. Stand up **Keycloak** with your own realm and clients — see
   [Identity & Keycloak](identity).
4. Supply all required **environment variables / secrets** — see
   [Configuration](configuration).
5. Run database **migrations** (`castor migrate`) and, if desired, load demo
   fixtures (`castor fixture`).
6. **Build Osoma** with your `VITE_*` values (they are baked in at build time).

```{admonition} Before production
:class: warning
Review the README's public-preview caveats and
[`SECURITY.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/SECURITY.md).
In particular: confirm authentication and tenant isolation for your model, back
credential storage with real encryption or a managed secret store, and replace
every `change-me-dev-only` value.
```

## The project's own deployment (reference)

Metadatapp's hosted instance deploys via an operator-specific GitHub Actions
workflow kept in the private operations repository (not part of this public
tree), since it's bound to Neuronautix's own hosting secrets.

You do not need Clever Cloud — any host that can run the Docker stack works.
