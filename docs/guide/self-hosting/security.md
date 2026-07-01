# Security & data protection

This page summarizes how Metadatapp protects data and what an operator must verify
before production. For canonical detail see
[`docs/CREDENTIALS.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/docs/CREDENTIALS.md),
[`SECURITY.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/SECURITY.md),
and [`ARCHITECTURE.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/ARCHITECTURE.md).

## Tenant isolation

Every record belongs to an **Account** (organization). Tenant-scoped entities
implement an account-aware contract; on creation the current user's account is
attached, and on read, providers and voters check that the record's account matches
the caller's. The result: **one organization cannot see another's data**.

```{admonition} Verify before production
:class: warning
Cross-tenant *write* protection relies on voters, and admin-screen gating is partly
enforced in the frontend. Before production, **verify backend authorization for
your model** — including cross-tenant write attempts — as called out in the
repository's public-preview caveats.
```

## Credential handling

The platform is designed so the **browser never holds external secrets**:

- Users enter Connected App and AI-provider credentials in Osoma; the API stores
  them **server-side** and returns only **masked hints**.
- To rotate a secret, type the new value; leaving a field blank keeps the stored
  one (partial updates are supported).
- Credentials are scoped to the account/user.

```{admonition} Production secret storage
:class: important
Back credential storage with **deployment-grade encryption or a managed secret
store** before advertising full external-provider support. This is on the release
checklist in `docs/CREDENTIALS.md`.
```

## Authentication & tokens

- Identity is **Keycloak OIDC** (PKCE in the browser). See [Identity & Keycloak](identity).
- The API firewall is **stateless**; access to `^/*` requires an authenticated user
  (`/docs` is public).
- The browser stores its token in `localStorage` today — a documented trade-off in
  the architecture notes; evaluate it against your threat model.

## Secrets hygiene

Never commit production secrets. The committed `.env` files contain **dev-only
placeholders**. The full list of values to set as secrets is in
[Configuration](configuration.md#secrets-you-must-set-never-commit). Replace every
`change-me-dev-only` value.

## Reporting vulnerabilities

Do not open a public issue for security problems. Follow the process in
[`SECURITY.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/SECURITY.md).
