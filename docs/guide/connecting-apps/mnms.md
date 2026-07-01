# MNMS (BIDS Neuroimaging Fields)

> MNMS is a bundled reference for MRI neuroimaging metadata fields drawn from the BIDS (Brain Imaging Data Structure) specification. Connecting it lets you browse and import standard MRI fields into your metadata schemas.

## At a glance

| | |
| --- | --- |
| Integration code | `mnms` |
| Direction | Browse + import |
| External URL | None — data is bundled; no external connection is made |

## Credentials you'll need

_No credentials are required._

## Where to get them

No credentials are required; the field catalogue is embedded in Metadatapp.

## Connect it

Follow the [standard connection steps](index.md#how-to-connect-any-app): open
**Connected Apps → App Directory → MNMS → Edit Settings**, leave URL and
credentials blank, **Test connection**, then save.

```{note}
Credentials are stored server-side and shown afterward only as a masked hint.
Leave a field blank when editing to keep its current value.
```

## What gets synced

- MRI / neuroimaging metadata field definitions (name, description, data type, unit) sourced from the BIDS specification

## Notes

- Fields include common MRI parameters such as `RepetitionTime`, `EchoTime`, `MagneticFieldStrength`, and many others.
- The canonical reference for these fields is the [BIDS specification](https://bids-specification.readthedocs.io/en/stable/).
- Because data is bundled, syncs are instant and work offline.
