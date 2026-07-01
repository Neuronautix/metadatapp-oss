# FAQ & troubleshooting

## Setup

**`castor` isn't found after I installed it.**
The installer drops the binary in `~/.local/bin`. Make sure that directory is on
your `PATH`. See [Local Setup](https://github.com/Neuronautix/metadatapp-oss/blob/main/README.md#local-setup).

**`castor start` takes a long time.**
The first run builds Docker images and installs backend and frontend dependencies —
several minutes is normal. Later runs are much faster.

**My browser warns about the certificate.**
Local development uses self-signed certificates for `*.metadatapp.test`. That
warning is expected locally; for a real deployment, provide real TLS certificates.

## Signing in

**Login works in the browser but every API call returns `401 Unauthorized`.**
This is almost always an OIDC mismatch — the realm, issuer, or audience disagree
between Keycloak, Osoma, and the API. Work through the
[401 troubleshooting checklist](../self-hosting/identity.md#troubleshooting-401s).

**Which demo accounts can I use?**
After `castor fixture`: `demo.admin@metadatapp.net` (admin) and
`demo.user@metadatapp.net` (user), password `Pa55w0rd`. Development only.

## Using the app

**A screen described in this guide isn't visible.**
It is probably turned off by a [feature flag](../administration/index.md#feature-flags),
or your role doesn't have access. Ask an administrator, or check Flag Studio
(`/admin/feature-flags`).

**I see demo data that doesn't match the real backend.**
Osoma may be in **mock data mode** (MSW), which serves in-browser demo data. Switch
to `real` on the `/__ops` page, or set `VITE_DATA_MODE=real` and rebuild. See
[Configuration](../self-hosting/configuration.md#osoma-variables-vite_-build-time).

## Connecting apps

**A sync returns `401` even though the connection test passed.**
Check the external URL (e.g. eLabFTW expects the instance root, *not* `/api/v2`) and
that the token hasn't expired. See the app's page under
[Connecting third-party apps](../connecting-apps/index).

**I changed a `VITE_*` value but nothing changed.**
`VITE_*` variables are baked into Osoma at **build time** — rebuild the frontend.

## AI

**The AI assistant does nothing / is missing.**
It's **disabled by default**. An administrator must enable it and configure a
provider in [AI Providers](../using-osoma/ai-providers), and the operator must set
`AI_ASSISTANT_ENABLED` and a provider — see
[Configuration → AI / LLM](../self-hosting/configuration.md#ai--llm).

**Will the AI change my data?**
No — it is read-only and any AI-suggested change must be human-approved before it is
saved. See the [governed-AI concept](../introduction/concepts.md#governed-human-reviewed-ai).

## Getting help

- **Usage & support:** [`SUPPORT.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/SUPPORT.md)
- **Security issues:** [`SECURITY.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/SECURITY.md)
  (do not open a public issue)
- **Contributing:** [`CONTRIBUTING.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/CONTRIBUTING.md)
