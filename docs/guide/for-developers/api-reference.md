# API reference

Metadatapp's backend is built on **API Platform**, which exposes every
`#[ApiResource]` as a REST endpoint and publishes an **OpenAPI 3.1** description.

## Live, on your instance

Every running instance serves interactive documentation and the raw spec:

- **Swagger UI:** `https://<your-api-host>/docs` (e.g. {{ local_api }}/docs locally)
- **OpenAPI JSON:** `https://<your-api-host>/docs.json`

These always reflect *your* deployment, including any resources you've added.

## Authentication

The API is protected by OIDC (Keycloak). Obtain an access token and send it as a
bearer token:

```text
Authorization: Bearer <access_token>
```

Administrators can also issue scoped personal access tokens from
[Settings → API Keys](../administration/index.md#api-keys). For how the realm,
issuer, and audience must line up, see [Identity & Keycloak](../self-hosting/identity).

## Browse the reference

The reference below is rendered from the committed specification
(`osoma/resources/metadatapp.json`). It is a snapshot for browsing; the live
`/docs.json` on your instance is authoritative.

```{raw} html
<redoc spec-url="../_static/openapi/metadatapp.json"></redoc>
<script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
```

```{note}
The interactive reference above loads in your browser from a CDN, so it needs an
internet connection to render. If you are reading an offline build, use your
instance's `/docs` page instead, or open
[`osoma/resources/metadatapp.json`](https://github.com/Neuronautix/metadatapp-oss/blob/main/osoma/resources/metadatapp.json)
directly.
```
