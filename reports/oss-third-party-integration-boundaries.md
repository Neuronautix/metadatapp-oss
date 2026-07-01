# OSS Third-Party Integration Boundaries

Date: 2026-06-11 (updated 2026-07-01 for the v0.2 release)

## Decision

Metadatapp can keep private, non-redistributable third-party integrations in the
private repository while publishing an OSS snapshot that excludes their source,
fixtures, tests, frontend affordances, logos, and documentation.

SoftMouse gave written authorization on 2026-07-01 for OSS inclusion, conditional
on the integration continuing to use only their documented public APIs and
standard authentication mechanisms, with users providing and managing their own
credentials, and no SoftMouse credentials, secrets, or customer data shipped in
the repository. The implementation was audited against these conditions and had
its remaining compliance issues fixed (a dead/commented-out token literal, a real
developer name used as fixture data, and a hardcoded dev-only user email in the
sync command) before being included. Any future SoftMouse-facing change must keep
meeting these same conditions.

Tecniplast DVC was cleared by the business for publication and Sensor Agent's
prior "internal/demo adapter" label was stale — both were already shipping in the
public OSS snapshot as of v0.1 and remain included in v0.2.

Precliniset was removed from the private repository entirely as of v0.2 and its
source is preserved only on the `archive/precliniset` branch; it was never
published to the OSS snapshot.

## Integration Classes

| Class | Meaning | OSS policy |
| --- | --- | --- |
| OSS-safe connector | Uses public APIs/docs or our own clean-room implementation, no proprietary examples/specs. | Keep in public snapshot. |
| Credential-only public connector | Talks to a public API but needs user-provided tokens. | Keep implementation, never ship credentials. |
| Private partner connector | Uses private/vendor API knowledge, partner docs, reverse-engineered payloads, or unapproved examples. | Exclude from public snapshot. Keep only in private repo. |
| Internal/demo adapter | Only works with our local demo/private infrastructure. | Either exclude or replace with generic stubs in OSS. |

## Current Classification

| Integration | Status | OSS action |
| --- | --- | --- |
| OSF | Credential-only public connector | Keep, with token entry UI and no bundled tokens. |
| protocols.io | Credential-only public connector | Keep connection-test implementation; keep token UI. |
| PreclinicalTrials.eu | OSS-safe public connector | Keep; uses public `GET https://preclinicaltrials.eu/api/external/viewable-protocols`. |
| NWB | OSS-safe import/export module | Keep; do not bundle binary `.nwb` fixtures. |
| CEDAR | Credential-only public connector | Keep fresh implementation only; no old resolver/tokens. |
| BioPortal | Credential-only public connector | Keep fresh implementation only; no old resolver/tokens. |
| ORCID/ROR/OLS lookup | OSS-safe public lookup | Keep. |
| eLabFTW | OSS-safe/credential public connector | Keep if only public API usage is present. |
| Fair3R | Internal/public project connector | Keep if schemas and endpoints are ours/public. |
| Precliniset | Removed from private repo | Excluded from OSS; source preserved on `archive/precliniset` only. |
| Tecniplast DVC | OSS-safe connector; business-cleared | Keep. Note: client hardcodes the real Tecniplast production hostname/endpoint paths/header name — this is normal vendor-API surface knowledge for a working connector, not a secret, but flagged for awareness. |
| SoftMouse | Credential-only public connector; vendor-authorized 2026-07-01 | Keep. Public `external/v1` REST API only, user-supplied credentials only, no bundled secrets/customer data. |
| Sensor Agent | OSS-safe connector | Keep. Makes real HTTP calls via a configurable env-var-driven endpoint (`SENSOR_AGENT_BASE_URL`/`SENSOR_AGENT_TOKEN`); no hardcoded credentials or real hostnames anywhere in the tree. Still named/branded as `App\Demo\Sensors`, `/demo/sensors/*`, and references a specific "Sovereign Sensor Agent"/Raspberry Pi product; a rename to generic public-facing naming is tracked as a follow-up, not a release blocker. |

## Precliniset Removal

Precliniset was removed from the private repository as part of the v0.2 release.
Its full source (backend module, migrations, frontend UI, fixtures) is preserved
on the `archive/precliniset` branch for historical reference; that branch is not
part of any public release.

## Cleanup Branch Workflow

The private repository remains the source of truth. The public repository is a
history-free snapshot generated from the cleanup branch:

1. Keep private partner integrations in private `main`.
2. Maintain `oss/do-not-merge-publication-filter` as a cleanup branch that
   removes private-only code and replaces public catalogs/docs with OSS-safe
   equivalents.
3. Rebase the cleanup branch onto the private source branch before publishing.
4. Publish only the resulting orphan `public-release` snapshot to
   `Neuronautix/metadatapp-oss`.

As of v0.2, the cleanup branch is rebuilt fresh from current `main` (rather than
rebased) because it had drifted weeks behind and a plain rebase would conflict
with unrelated feature work merged in the meantime. It no longer excludes
SoftMouse, Tecniplast, or Sensor Agent.

## Recommended Architecture

Longer term, define a small integration manifest per connector:

```yaml
code: osf
name: OSF
distribution: oss-safe
public_api_docs: true
requires_credentials: true
frontend_catalog: true
```

Allowed `distribution` values:

- `oss-safe`
- `oss-review-required`
- `private-only`

Then enforce this in the OSS cleanup process:

- OSS snapshot keeps only `oss-safe` connectors.
- `oss-review-required` connectors are excluded until approved.
- `private-only` connectors are always excluded from public snapshots.

This avoids relying on memory when new third-party apps are added.
