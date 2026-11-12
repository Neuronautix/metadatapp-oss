# Identity & Keycloak

Metadatapp authenticates users with **Keycloak** over OIDC. Getting identity right
is the single most important — and most error-prone — part of standing up your own
instance.

## The demo realm

The repository ships a ready-made realm at
`infrastructure/docker/services/keycloak/config/realm-demo.json`:

- **Realm:** `demo`.
- **Clients:**
  - `osoma` — public client for the SPA frontend.
  - `api-platform-swagger` — public client for the API's Swagger UI.
  - `api-platform-api` — confidential client for the API (resource server).
- **Realm roles (hierarchical):** `super_admin` → `admin` → `user`.
- **Seeded demo users** (loaded with fixtures): `demo.admin@metadatapp.net` and
  `demo.user@metadatapp.net` (password `Pa55w0rd`), plus others.

This is perfect for local evaluation and **must not** be reused in production.

## Standing up your own realm

For a real deployment, create your own realm and clients in Keycloak. At minimum:

1. Create a **realm** (e.g. `users`).
2. Create a **public client** for Osoma (e.g. `osoma`) with your domain as a valid
   redirect URI.
3. Create a **confidential client** for the API and note its **client secret**.
4. Ensure access tokens carry the **audience** your API expects — add an
   *Audience* mapper to the client if needed.
5. Create your **users and role mappings** (`user` / `admin` / `super_admin`).

## Make the three components agree

The realm, the Osoma client, and the API validator must all point at the **same
realm, issuer, and audience**. If they disagree, login appears to succeed in the
browser but **every API call returns `401 Unauthorized`**.

| Logical setting | API variable | Osoma variable | Must equal |
| --- | --- | --- | --- |
| Issuer (browser-facing) | `OIDC_SERVER_URL` | `VITE_OIDC_SERVER_URL` | the realm's `issuer` from `https://<auth-host>/realms/<realm>/.well-known/openid-configuration` |
| Issuer (server-to-server) | `OIDC_SERVER_URL_INTERNAL` | — | same realm, reachable inside the API container |
| OIDC client | — (validated via audience) | `VITE_OIDC_CLIENT_ID` | the Keycloak client id (e.g. `osoma`) |
| Token audience | `OIDC_AUD` | — | the `aud` claim the access token actually carries |

```{admonition} Realm and path gotcha
:class: note
The example files use the `demo` realm and include an `/oidc` path segment
(`.../oidc/realms/demo`) that the Docker stack itself does not always use. **Always
use the exact `issuer` value** returned by the realm's
`.well-known/openid-configuration` endpoint. When you rename the domain or realm,
update **both** the API and Osoma variables together — and **rebuild Osoma**, since
`VITE_*` values are baked in at build time.
```

## Troubleshooting 401s

If login redirects work but API requests return `401`:

1. In the browser console, decode the access token:
   `JSON.parse(atob(token.split('.')[1]))` and read its `iss` and `aud`.
2. Confirm `iss` matches the API's `OIDC_SERVER_URL` exactly, and that
   `curl https://<auth-host>/realms/<realm>/.well-known/openid-configuration`
   returns `200` (not `404`).
3. Confirm `aud` matches the API's `OIDC_AUD`.
4. After changing API variables, **restart the API and clear its cache** so the
   cached OIDC discovery document is refreshed.
