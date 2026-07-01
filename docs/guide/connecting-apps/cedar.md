# CEDAR

> CEDAR (the Center for Expanded Data Annotation and Retrieval) hosts metadata
> templates, elements, fields and instances. Metadatapp talks to CEDAR's
> resource-server REST API directly, mirroring the official
> [`cedar-artifact-rest-mcp`](https://github.com/metadatacenter/cedar-artifact-rest-mcp)
> tool surface one-to-one.

## At a glance

| | |
| --- | --- |
| Integration code | `cedar` |
| Direction | Artifact CRUD (fetch / create / update / delete) + validate + import |
| External URL | `https://resource.metadatacenter.org` (leave blank to use the public CEDAR resource API) |

## Credentials you'll need

API Key (required)
: A CEDAR API key. May be supplied bare or already prefixed with `apiKey ` —
Metadatapp normalizes it to the `Authorization: apiKey <key>` header CEDAR
expects, exactly like the CEDAR MCP.

## Where to get them

Sign in to CEDAR and copy your API key from your account profile.

## Connect it

Follow the [standard connection steps](index.md#how-to-connect-any-app): open
**Connected Apps → App Directory → CEDAR → Edit Settings**, enter the external
URL and the credentials above, **Test connection**, then save.

```{note}
Credentials are stored server-side and shown afterward only as a masked hint.
Leave a field blank when editing to keep its current value.
```

## What you can do

Metadatapp proxies the CEDAR resource server behind your account so the full
artifact lifecycle is available without exposing your API key to the browser.
The artifact surface matches the CEDAR MCP exactly, for all four artifact kinds
(`template`, `element`, `field`, `instance`):

| Operation | Endpoint | CEDAR MCP equivalent |
| --- | --- | --- |
| Test connection | `POST /connected_apps/{id}/cedar/test-connection` | — |
| Fetch by `@id` | `GET /connected_apps/{id}/cedar/artifacts/{type}?iri=…` | `get_template` / `get_element` / `get_field` / `get_instance` |
| Create | `POST /connected_apps/{id}/cedar/artifacts/{type}` | `create_*` |
| Update | `POST /connected_apps/{id}/cedar/artifacts/{type}/update` | `update_*` |
| Delete | `POST /connected_apps/{id}/cedar/artifacts/{type}/delete` | `delete_*` |
| Validate | `POST /connected_apps/{id}/cedar/validate` | `validate_artifact` |
| Import a template | `POST /connected_apps/{id}/cedar/import-template` | — |

- **Artifacts are addressed by their full CEDAR IRI** (`@id`); URL-encoding is
  handled for you — pass the plain IRI.
- **Create** nulls the top-level `@id` on submission so CEDAR assigns the
  authoritative one and returns the created artifact.
- **Validate** auto-detects the artifact kind from its `@type` (you can override
  it with a `type` field) and returns CEDAR's authoritative
  `{ "validates": …, "warnings": […], "errors": […] }` report.
- **Import a template** fetches a CEDAR template by IRI and stores it locally as
  an external template, ready to crosswalk into Metadatapp forms.

## Notes

- Folders, search, users, permissions and categories are out of scope, matching
  the CEDAR MCP's deliberate artifact-only focus.
- Validation is read-only — it never creates an artifact on the server.
- Delete is destructive and irreversible; confirm before calling it.
