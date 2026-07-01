# eLabFTW

> eLabFTW is an electronic lab notebook. Connecting it imports your experiments.

## At a glance

| | |
| --- | --- |
| Integration code | `elabftw` |
| Direction | Import (external → Metadatapp) |
| External URL | the **instance root**, e.g. `https://your-elabftw.example` — do **not** append `/api/v2` |

## Credentials you'll need

API Key (required)
: An eLabFTW API key.

## Where to get them

In eLabFTW, open **User panel → API keys** and create a key with the access level you want Metadatapp to have (read-only is enough for import).

## Connect it

Follow the [standard connection steps](index.md#how-to-connect-any-app): open
**Connected Apps → App Directory → eLabFTW → Edit Settings**, enter the external
URL and the credentials above, **Test connection**, then save and trigger a sync.

```{note}
Credentials are stored server-side and shown afterward only as a masked hint.
Leave a field blank when editing to keep its current value.
```

## What gets synced

- Experiments

## Notes

- The most common error here is putting `/api/v2` in the external URL. Use only the instance root; Metadatapp adds the API path itself.
