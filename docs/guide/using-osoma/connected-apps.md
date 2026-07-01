# Connected Apps in the UI

This page describes the **screens**. For the step-by-step credentials and sync
details of each integration, see
[Connecting third-party apps](../connecting-apps/index).

## App Directory

**Connected Apps → App Directory** (`/connected-apps`) shows a card for every
available integration. Each card displays:

- the app name, logo, and a short description;
- its status (active / inactive);
- the last sync time, when available.

Click a card to open its detail page.

## App detail page

The detail page (`/connected-apps/:appId`) is where you actually operate an
integration:

- **Edit Settings** opens the configuration dialog where you enter the external
  URL and credentials. Fields are app-specific — see the app's page under
  [Connecting third-party apps](../connecting-apps/index).
- **Test connection** validates your credentials against the external system
  without importing anything.
- **Trigger sync** runs the import (or, for FAIR3R, the publish).
- App-specific tabs/panels appear here too — for example the SoftMouse import
  tab, the FAIR3R publishing panel, the NIH CDE browser, the Tecniplast DVC
  data panels, the **CEDAR artifact workbench** (fetch / create / update / delete /
  validate / import templates for crosswalking — see [CEDAR](../connecting-apps/cedar)),
  and the **JAX Phenome (MPD) measure browser** (see
  [JAX Phenome](../connecting-apps/jax_phenome)).

```{note}
After you save credentials, they are never shown again — only a masked hint. To
change one, type the new value; leave a field blank to keep the stored value.
```

## Configuration dialog

The **Edit Settings** dialog adapts to the app:

- Apps with dedicated fields (e.g. SoftMouse's username/password, FAIR3R's access
  token plus dataset/organization) show those labelled inputs.
- Apps without dedicated fields fall back to a single **API Token / Password**
  field.
- Public apps (PreclinicalTrials.eu, NIH CDE) need no secret at all and can be
  connected by URL alone.

## Live Showcase

**Connected Apps → Live Showcase** is a demonstration view that highlights what
the integrations can surface. It is feature-gated and intended for demos rather
than day-to-day configuration.
