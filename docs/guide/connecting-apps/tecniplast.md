# Tecniplast DVC

> Tecniplast DVC (Digital Ventilated Cage) provides continuous cage-activity data. Connecting it imports its metric definitions and unlocks the DVC data panels.

## At a glance

| | |
| --- | --- |
| Integration code | `tecniplast` |
| Direction | Import (external → Metadatapp) |
| External URL | your DVC API URL (placeholder `https://your-dvc-api.example`) |

## Credentials you'll need

Access Token (required)
: A DVC API access token.

Refresh Token (optional)
: A DVC refresh token, if your deployment issues one.

## Where to get them

Obtain an access token (and optional refresh token) from your Tecniplast DVC deployment or administrator.

## Connect it

Follow the [standard connection steps](index.md#how-to-connect-any-app): open
**Connected Apps → App Directory → Tecniplast DVC → Edit Settings**, enter the external
URL and the credentials above, **Test connection**, then save and trigger a sync.

```{note}
Credentials are stored server-side and shown afterward only as a masked hint.
Leave a field blank when editing to keep its current value.
```

## What gets synced

- Variables (metric definitions)
- Cage-activity data through the DVC tasks panel

## Notes

- For shared service accounts you can also configure DVC through deployment environment variables instead of per-user credentials.
