# Connecting third-party apps

Metadatapp integrates with external laboratory and research systems through
**Connected Apps**. Each one is a server-side plugin that follows a common
contract, so connecting any of them works the same way: you enter credentials in
Osoma, and the API uses them to fetch from (or push to) the external system.

## The security model (read this first)

Three rules apply to **every** integration on the following pages:

1. **The browser never calls the external system.** Osoma sends your credentials
   to the Metadatapp API; the API holds them and makes the external calls.
2. **Credentials are stored server-side and never shown again.** After you save,
   the API returns only a masked *hint* (for example `••••abcd`). To rotate a
   secret, type the new value; leave a field **blank to keep the current value**.
3. **Credentials are scoped to your account/organization.** Another tenant cannot
   see or use them.

For the underlying field names and the deployment-time encryption requirements,
see [`docs/CREDENTIALS.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/docs/CREDENTIALS.md).

## How to connect any app

The steps are identical for every integration; the per-app pages only differ in
*which credentials* you enter and *what gets synced*.

1. In Osoma, open **Connected Apps → App Directory** (`/connected-apps`).
2. Click the card for the app you want to connect.
3. On the detail page, open **Edit Settings** to open the configuration dialog.
4. Enter the **external URL** (or leave blank to use the app's public default,
   where supported) and the **credentials** listed on that app's page.
5. Click **Test connection** to confirm the credentials work.
6. Save, then **trigger a sync** to import or push data.

```{tip}
If a sync returns `401 Unauthorized` even though the test passed, re-check that
the external URL has no trailing `/api` segment where the app expects only the
root (eLabFTW is the classic case), and that the token has not expired.
```

## Supported integrations

The authoritative list of integration codes is the `AppCode` enum in
`api/src/Enum/AppCode.php`. Each code below has its own page.

| App | Code | Direction | Credentials |
| --- | --- | --- | --- |
| [SoftMouse](softmouse) | `softmouse` | Import | Username + password |
| [eLabFTW](elabftw) | `elabftw` | Import | API key |
| [OSF](osf) | `osf` | Import | Access token |
| [protocols.io](protocolio) | `protocolio` | Import (preview) | Access token |
| [PreclinicalTrials.eu](preclinicaltrials) | `preclinicaltrials` | Import | None (public) |
| [Tecniplast DVC](tecniplast) | `tecniplast` | Import | Access token |
| [FAIR3R](fair3r) | `fair3r` | Publish (push) | Access token |
| [CEDAR](cedar) | `cedar` | Artifact CRUD + validate + import | API key |
| [BioPortal](bioportal) | `bioportal` | Validate (preview) | API key |
| [NIH CDE Repository](nih_cde) | `nih_cde` | Browse + import | None (public) |
| [JAX Phenome (MPD)](jax_phenome) | `jax_phenome` | Browse | None (public) |
| [IMPReSS (IMPC)](impc) | `impc` | Browse | None (public) |
| [MNMS (BIDS Neuroimaging Fields)](mnms) | `mnms` | Browse + import | None (bundled) |
| [Guidelines Hub](guidelines_hub) | `guidelines_hub` | Browse | None (bundled) |
| [Sovereign Sensor Agent](sensor_agent) | `sensor_agent` | Read-only proxy | Deployment env |

```{admonition} Availability varies by build
:class: note
Every integration above is listed in the app, but how complete each one is depends
on your build and deployment — and the open-source distribution does not include
the adapter code for every commercial system. If an integration appears in the UI
but a sync fails with "no service found", that adapter is not part of your build.
eLabFTW is the most complete adapter in the public preview.
```

```{toctree}
:maxdepth: 1
:hidden:

softmouse
elabftw
osf
protocolio
preclinicaltrials
tecniplast
fair3r
cedar
bioportal
nih_cde
jax_phenome
impc
mnms
guidelines_hub
sensor_agent
```
