# Glossary

```{glossary}
Account
  An organization / tenant. All data belongs to exactly one Account, which is the
  isolation boundary between organizations. See
  [multi-tenancy](../introduction/concepts.md#organizations-users-and-isolation-multi-tenancy).

API Platform
  The PHP framework (on top of Symfony) that exposes Metadatapp's entities as a
  REST API.

ARRIVE
  *Animal Research: Reporting of In Vivo Experiments* — a reporting checklist for
  preclinical animal studies. Metadatapp generates ARRIVE helper reports.

Assay
  A procedure or standard operating procedure applied within a study.

Castor
  The task runner used across the repository (`castor start`, `castor qa:all`, …).

Connected App
  A server-side adapter to an external research system (eLabFTW, SoftMouse, OSF,
  FAIR3R, Tecniplast DVC, …). See [Connecting third-party apps](../connecting-apps/index).

Connected resource link
  A provenance pointer from a study to a record in an external system. Travels
  inside metadata exports.

Curation
  The review-first workflow of turning raw spreadsheet data into clean, standardized
  metadata, with AI-assisted proposals you approve. See
  [Importing data](../using-osoma/importing-data).

Dataset
  A data product attached to your research, with a format and a FAIR score.

DVC
  *Digital Ventilated Cage* (Tecniplast) — provides continuous cage-activity data.

FAIR
  *Findable, Accessible, Interoperable, Reusable* — the principles Metadatapp helps
  you measure and meet. See [FAIR assessment](../introduction/concepts.md#fair-assessment).

FAIR²
  The project's label for its JSON-LD export, emphasizing machine-actionable
  interoperability.

Feature flag
  A switch that turns parts of the app on or off per deployment. Managed in Flag
  Studio. See [Feature flags](../administration/index.md#feature-flags).

Investigation
  The top-level research effort; contains studies. The first level of the ISA model.

ISA model
  *Investigation → Study → Assay* — the structure Metadatapp uses to organize
  research metadata.

JSON-LD
  *JSON for Linking Data* — a linked-data serialization used in Metadatapp's
  interoperable exports.

Keycloak
  The OIDC identity provider that authenticates users. See
  [Identity & Keycloak](../self-hosting/identity).

MCP
  *Model Context Protocol* — the read-only bridge through which the AI assistant
  queries data.

Mercure
  The hub that pushes real-time updates to the browser.

MSW
  *Mock Service Worker* — the in-browser mock layer used when Osoma runs in mock
  data mode.

Osoma
  Metadatapp's React/Vite frontend — the app you use in the browser.

RO-Crate
  *Research Object Crate* — a packaged (ZIP) bundle of data plus standardized
  metadata, used for archiving and repository submission.

Study
  A study within an investigation. (In the API this is the `Experiment` resource,
  exposed at `/study`.)

Subject
  An individual research animal (mouse, rat, zebrafish, …).

Tenant
  See {term}`Account`.

Zefix
  The animal-care domain (cages, lines, breeding batches, mortality, cryo,
  environmental monitoring) surfaced in the relevant deployments.
```
