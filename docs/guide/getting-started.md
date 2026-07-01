# Getting started

This page assumes you have a **running instance**. If you don't yet, follow
[Local Setup in the repository README](https://github.com/Neuronautix/metadatapp-oss/blob/main/README.md#local-setup)
to install Castor, start the stack with `castor start`, and load demo data with
`castor fixture`.

## Default local URLs

Once `castor start` finishes, the stack is reachable at:

| Component | URL |
| --- | --- |
| Osoma frontend | {{ local_osoma }} |
| API documentation | {{ local_api }}/docs |
| Keycloak | https://auth.metadatapp.test/oidc/ |

The local stack uses self-signed certificates, so your browser will warn you the
first time — that is expected for local development.

## Signing in

Open {{ local_osoma }} and choose **Login with Keycloak**. After loading the demo
fixtures (`castor fixture`), two accounts are available:

| Role | Email | Password |
| --- | --- | --- |
| Administrator | `demo.admin@metadatapp.net` | `Pa55w0rd` |
| Standard user | `demo.user@metadatapp.net` | `Pa55w0rd` |

```{warning}
These are development-only credentials for the demo realm. Never reuse them in a
deployment. For your own instance, create real users and a real realm — see
**Configuration and Environments** in the repository README.
```

## Data and auth modes (for testing)

Osoma can run against live API data or against in-browser mock data. The toggles
live on a hidden operations page at `/__ops`:

- **Data mode** — `real` calls the API backend; `mock` serves local demo data via
  MSW (no backend needed).
- **Auth mode** — `real` runs the full Keycloak flow; `bypass` grants an instant
  local session.

For a real instance you want `real` / `real`. Mock mode is useful for frontend
demos and development without a backend. See
[Using Osoma](using-osoma/index) for the full screen-by-screen tour.

## Next steps

- [Connect your first external app](connecting-apps/index)
- [Explore the Osoma interface](using-osoma/index)
