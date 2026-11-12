# Sovereign Sensor Agent

> The Sovereign Sensor Agent exposes live environmental sensor data from an edge/local agent. Metadatapp proxies it read-only.

## At a glance

| | |
| --- | --- |
| Integration code | `sensor_agent` |
| Direction | Read-only proxy |
| External URL | configured through the `SENSOR_AGENT_BASE_URL` deployment variable |

## Credentials you'll need

_No credentials are required for the default configuration._

## Where to get them

This integration is configured by the **instance operator through environment variables**, not per-user in the Connected Apps dialog.

## Connect it

Follow the [standard connection steps](index.md#how-to-connect-any-app): open
**Connected Apps → App Directory → Sovereign Sensor Agent → Edit Settings**, enter the external
URL and the credentials above, **Test connection**, then save and trigger a sync.

```{note}
Credentials are stored server-side and shown afterward only as a masked hint.
Leave a field blank when editing to keep its current value.
```

## What gets synced

- Live sensor readings (read-only)

## Notes

- Unlike the other apps, the sensor agent is set up via deployment environment variables: `SENSOR_AGENT_ENABLED`, `SENSOR_AGENT_BASE_URL`, `SENSOR_AGENT_TOKEN`, and `SENSOR_AGENT_TIMEOUT` (see `api/.env.example`).
- It is **disabled by default** (`SENSOR_AGENT_ENABLED=false`). Enable it only when you have a sensor agent to point at.
