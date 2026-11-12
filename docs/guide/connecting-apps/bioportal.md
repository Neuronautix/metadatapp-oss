# BioPortal

> BioPortal is a repository of biomedical ontologies.

## At a glance

| | |
| --- | --- |
| Integration code | `bioportal` |
| Direction | Validate (preview) |
| External URL | `https://data.bioontology.org` (leave blank to use the public BioPortal API) |

## Credentials you'll need

API Key (required)
: A BioPortal API key.

## Where to get them

Create a free BioPortal account; your API key is shown in your account settings.

## Connect it

Follow the [standard connection steps](index.md#how-to-connect-any-app): open
**Connected Apps → App Directory → BioPortal → Edit Settings**, enter the external
URL and the credentials above, **Test connection**, then save and trigger a sync.

```{note}
Credentials are stored server-side and shown afterward only as a masked hint.
Leave a field blank when editing to keep its current value.
```

## What gets synced

_Nothing is imported yet in the current preview (see notes below)._

## Notes

- This integration is in **preview**: API-key validation works, but ontology import is not yet wired.
