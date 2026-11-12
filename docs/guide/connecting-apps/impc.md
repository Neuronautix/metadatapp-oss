# IMPReSS (International Mouse Phenotyping Resource of Standardised Screens)

> IMPReSS is the community-curated catalogue of standardized phenotyping screens
> maintained by the International Mouse Phenotyping Consortium (IMPC). Connecting it
> lets you search standardized phenotyping **procedures** (Open Field, Acoustic
> Startle / PPI, Grip Strength, …) and inspect their **parameters** and ontology
> mappings — the standardized resources behind behavioral and physiological studies.

## At a glance

| | |
| --- | --- |
| Integration code | `impc` |
| Direction | Browse |
| External URL | `https://api.mousephenotype.org` (leave blank to use the public IMPReSS API) |

## Credentials you'll need

_No credentials are required; IMPReSS is a public resource._

## Where to get them

No credentials are required; the IMPReSS catalogue served by the IMPC is public.

## Connect it

Follow the [standard connection steps](index.md#how-to-connect-any-app): open
**Connected Apps → App Directory → IMPReSS (IMPC) → Edit Settings**, enter the
external URL (or leave it blank to use the public IMPReSS API), **Test connection**,
then save.

```{note}
No credentials are stored for this integration. IMPReSS is a read-only, public
browse — there is nothing to rotate or mask.
```

## What gets synced

- Nothing is persisted or imported. IMPReSS is a live browse of standardized
  phenotyping **pipelines**, **procedures**, and **parameters** in the IMPC
  catalogue, including ontology annotations, while you explore the resource in the app.

## What you can do

Metadatapp proxies the public IMPReSS API behind your account, so the browse surface
is available without the frontend ever calling `mousephenotype.org` directly. This
mirrors the JAX Phenome and NIH CDE standardized-resource integrations, so the same
search-and-inspect workflow applies across all of them:

| Operation | Endpoint |
| --- | --- |
| Test connection | `GET /connected_apps/{id}/impc/test-connection` |
| Search procedures | `GET /connected_apps/{id}/impc/procedures` |
| Get one procedure (with parameters) | `GET /connected_apps/{id}/impc/procedures/{procedureId}` |
| List pipelines | `GET /connected_apps/{id}/impc/pipelines` |

The procedure detail is keyed by the upstream numeric procedure id (`procID`).
IMPReSS serves a procedure's parameters from a dedicated endpoint
(`/impress/parameter/belongingtoprocedure/full/{procID}`); Metadatapp fetches both
and stitches the parameters into the procedure for the inspector.

## Developer: live ping

A one-off console command verifies connectivity against the live IMPReSS API without
going through the UI — useful when checking egress or debugging a deployment:

```bash
docker compose -p metadatapp --profile default \
  -f infrastructure/docker/docker-compose.yml \
  exec api sh -lc 'cd /var/www/api && bin/console app:impc:ping'
```

Pass an optional connected-app id and a `--search` term to exercise the procedure
search path (`bin/console app:impc:ping <app-id> --search="open field"`).

## Notes

- This is a public, read-only browse against the IMPReSS catalogue served by the
  IMPC API; no data is written back and nothing is stored locally.
- Data comes from the International Mouse Phenotyping Consortium: <https://www.mousephenotype.org/impress/>.
- API documentation: <https://www.mousephenotype.org/help/programmatic-data-access/>.
