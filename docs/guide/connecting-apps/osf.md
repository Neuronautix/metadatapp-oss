# OSF (Open Science Framework)

> OSF is an open research collaboration platform. Connecting it imports projects as studies along with their linked resources.

## At a glance

| | |
| --- | --- |
| Integration code | `osf` |
| Direction | Import (external → Metadatapp) |
| External URL | `https://osf.io` (you can also leave it blank to use the default) |

## Credentials you'll need

Access Token (required)
: An OSF personal access token.

## Where to get them

In OSF, go to **Settings → Personal access tokens → Create token** and grant the scopes you need (read access is enough for import).

## Connect it

Follow the [standard connection steps](index.md#how-to-connect-any-app): open
**Connected Apps → App Directory → OSF (Open Science Framework) → Edit Settings**, enter the external
URL and the credentials above, **Test connection**, then save and trigger a sync.

```{note}
Credentials are stored server-side and shown afterward only as a masked hint.
Leave a field blank when editing to keep its current value.
```

## What gets synced

- Studies (from OSF projects)
- Linked/connected resources

