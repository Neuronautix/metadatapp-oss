# FAIR3R

> FAIR3R is a CKAN-based FAIR validation and publishing service. Unlike the import integrations, this one **pushes** dataset metadata out to a CKAN datastore.

## At a glance

| | |
| --- | --- |
| Integration code | `fair3r` |
| Direction | Publish (Metadatapp → external) |
| External URL | `https://validation.fair3r.fr` |

## Credentials you'll need

Access Token (required)
: A FAIR3R / CKAN access token.

Target Dataset (optional)
: An existing CKAN dataset slug to publish into, e.g. `damien-test-manuel`.

Owner Organization (optional)
: A CKAN organization id or name that owns the dataset.

## Where to get them

Get an access token from your FAIR3R / CKAN account, and note the dataset slug and organization you want to publish into.

## Connect it

Follow the [standard connection steps](index.md#how-to-connect-any-app): open
**Connected Apps → App Directory → FAIR3R → Edit Settings**, enter the external
URL and the credentials above, **Test connection**, then save and trigger a sync.

```{note}
Credentials are stored server-side and shown afterward only as a masked hint.
Leave a field blank when editing to keep its current value.
```

## What gets synced

- Dataset metadata is published outward to the configured CKAN datastore

## Notes

- This is the only outbound integration in the default set: it writes to the external system rather than reading from it.
