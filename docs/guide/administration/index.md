# Administration

This section is for **administrators** of a running instance. Administrative
screens live under System Administration in the sidebar and are gated by an admin
role.

```{toctree}
:hidden:
```

## Roles

Metadatapp has three roles, issued by Keycloak and carried in your sign-in token:

| Role | Can do |
| --- | --- |
| **Standard user** (`user`) | Work with research records within their organization. |
| **Administrator** (`admin`) | Everything a user can, plus the admin screens below. |
| **Super administrator** (`super_admin`) | Cross-organization (Account) management. |

In the Osoma UI these map to `VIEWER` / `EDITOR` / `ADMIN` rights derived from your
`organizationRole`: viewers read, editors write, admins administer.

```{admonition} Verify backend authorization for your deployment
:class: warning
In the current public preview, much of the admin-screen gating is enforced in the
**frontend** (an `AdminGuard`), on top of authenticated access and tenant-scoped
data providers. Treat the admin UI gating as UX, and **verify backend access
control matches your security model** before production — this is called out in the
repository README's public-preview caveats.
```

## Users

**Users** (`/users`) — list, create, view, and edit users. Filter by status
(active / invited / suspended) and access level (viewer / editor / admin); export
to CSV. Routes: `/users`, `/users/new`, `/users/:id`, `/users/:id/edit`.

Because identity is backed by Keycloak, creating/altering who can actually sign in
ultimately involves your Keycloak realm — see [Identity & Keycloak](../self-hosting/identity).

## Organizations

**Organizations** (`/organizations`) — list, view, and edit the organizations
(Accounts) in your instance; filter by type (university / biotech / hospital /
consortium) and export to CSV. Each Account is a tenant boundary — see
[Key concepts](../introduction/concepts.md#organizations-users-and-isolation-multi-tenancy).

## API Keys

**Settings → API Keys** (`/settings/api-keys`, admin-only) — generate scoped
personal access tokens for programmatic API access, view masked previews with
created/last-used metadata, and revoke them.

## Audit Log

**Audit Log** (`/audit`, admin-only, gated by `feature.auditLogs`) — a
system-wide activity trail. Filter by actor, action, resource type, and date range.

## AI Providers

**Settings → AI Providers** (`/settings/AI-providers`, admin-only) — configure
OpenAI/Anthropic keys, pick default models, enable/disable providers, and test
connections. Full walkthrough in [AI Providers](../using-osoma/ai-providers).

## Connected Applications

**Connected Apps → App Directory** (`/connected-apps`, gated by
`feature.connectedApps`) — activate/deactivate integrations, view last-sync state,
and manage connections. The per-app how-to is in
[Connecting third-party apps](../connecting-apps/index).

## Feature flags

Much of what appears in Osoma is controlled by **feature flags**. Administrators
manage them in **Flag Studio** (`/admin/feature-flags`): toggle individual flags,
apply a **preset**, reset to defaults, or bulk-toggle by category.

### Presets

A preset is a named bundle of flags that tailors the app to a deployment profile.
Built-in presets include `professional`, `demo` (everything on), `zefix`
(zebrafish facility), `tecniplast` (rodent facility), `studyal`, and `god` (full
access).

```{admonition} Enforced presets
:class: note
An organization can have an **enforced** preset (the `featurePreset` on its
Account). When enforced, the server returns the preset as read-only and Flag
Studio disables manual edits — so every user in that organization gets a
consistent, locked feature set.
```

### Notable flags

| Flag | Gates |
| --- | --- |
| `metadatapp-feat.enabled` | Core metadata CRUD surfaces and the Metadata Catalog |
| `feature.connectedApps` | The Connected Applications module (`feature.connectedApps.deepDive` adds advanced stats) |
| `feature.curationWorkflow.enabled` | The AI-assisted curation workflow |
| `feature.curateGpt.enabled` | The CurateGPT curation copilot |
| `import.enabled` / `dataEntry.enabled` | The CSV import wizard / grid data entry |
| `feature.auditLogs` | The Audit Log |
| `feature.observatory` | The Observatory command center |
| `zefix.enabled` / `cages.enabled` / `atmp.enabled` | The Zefix, cages, and ATMP modules |
| `dvc.*` | The Tecniplast DVC analytics suite |

The full catalogue lives in `osoma/src/feature-flags/flags.ts`.
