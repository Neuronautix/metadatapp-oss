# PreclinicalTrials.eu

> PreclinicalTrials.eu is a registry of preclinical study protocols. Connecting it imports published protocols as investigations.

## At a glance

| | |
| --- | --- |
| Integration code | `preclinicaltrials` |
| Direction | Import (external → Metadatapp) |
| External URL | the viewable-protocols endpoint (`https://preclinicaltrials.eu/api/external/viewable-protocols`); leave blank to use the public published / no-embargo endpoint |

## Credentials you'll need

_No credentials are required for the default configuration._

## Where to get them

No credentials are required for the public published protocols. Supply a custom endpoint only if you have access to a non-public viewable-protocols feed.

## Connect it

Follow the [standard connection steps](index.md#how-to-connect-any-app): open
**Connected Apps → App Directory → PreclinicalTrials.eu → Edit Settings**, enter the external
URL and the credentials above, **Test connection**, then save and trigger a sync.

```{note}
Credentials are stored server-side and shown afterward only as a masked hint.
Leave a field blank when editing to keep its current value.
```

## What gets synced

- Investigations (published protocols)

## Notes

- Because the default feed is public, you can connect this app without entering any secret.
