# JAX Phenome (Mouse Phenome Database / MPD)

> The Mouse Phenome Database (MPD) at The Jackson Laboratory is a public resource for exploring mouse physiology and behavior through genetics and genomics. Connecting it lets you browse curated phenotype measures across mouse strains, including ontology annotations.

## At a glance

| | |
| --- | --- |
| Integration code | `jax_phenome` |
| Direction | Browse |
| External URL | `https://phenome.jax.org` (leave blank to use the public MPD API) |

## Credentials you'll need

_No credentials are required; JAX Phenome is a public resource._

## Where to get them

No credentials are required; the Mouse Phenome Database is public.

## Connect it

Follow the [standard connection steps](index.md#how-to-connect-any-app): open
**Connected Apps → App Directory → JAX Phenome (MPD) → Edit Settings**, enter the external
URL (or leave it blank to use the public MPD API), **Test connection**, then save.

```{note}
No credentials are stored for this integration. JAX Phenome is a read-only, public
browse — there is nothing to rotate or mask.
```

## What gets synced

- Nothing is persisted or imported. JAX Phenome is a live browse of curated mouse
  phenotype **measures** and **strains** in the Mouse Phenome Database, including
  ontology annotations, while you explore the resource in the app.

## What you can do

Metadatapp proxies the public MPD API behind your account, so the browse surface
is available without the frontend ever calling `phenome.jax.org` directly:

| Operation | Endpoint |
| --- | --- |
| Test connection | `GET /connected_apps/{id}/jax-phenome/test-connection` |
| Search measures | `GET /connected_apps/{id}/jax-phenome/measures` |
| Get one measure | `GET /connected_apps/{id}/jax-phenome/measures/{measureId}` |
| List strains | `GET /connected_apps/{id}/jax-phenome/strains` |

## Developer: live ping

A one-off console command verifies connectivity against the live MPD API without
going through the UI — useful when checking egress or debugging a deployment:

```bash
docker compose -p metadatapp --profile default \
  -f infrastructure/docker/docker-compose.yml \
  exec api sh -lc 'cd /var/www/api && bin/console app:jax-phenome:ping'
```

Pass an optional connected-app id and a `--search` term to exercise the measure
search path (`bin/console app:jax-phenome:ping <app-id> --search="body weight"`).

## Notes

- This is a public, read-only browse against the Mouse Phenome Database (MPD) API;
  no data is written back and nothing is stored locally.
- Data comes from the Mouse Phenome Database at The Jackson Laboratory: <https://phenome.jax.org>.
- API documentation: <https://phenome.jax.org/about/api>.
