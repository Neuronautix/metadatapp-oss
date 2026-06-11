# OSS Third-Party Integration Boundaries

Date: 2026-06-11

## Decision

Metadatapp can keep private, non-redistributable third-party integrations in the
private repository while publishing an OSS snapshot that excludes their source,
fixtures, tests, frontend affordances, logos, and documentation.

Private partner connectors stay private until the partner explicitly authorizes
API sharing or we replace the implementation with a clean-room, public contract.

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
| Tecniplast DVC | OSS-approved connector | Keep. |
| Sensor Agent | OSS-approved connector | Keep. |
| Private colony management connector | Private partner connector; no API sharing approval | Exclude from OSS snapshot. |

## Private Connector OSS Exclusion Inventory

Remove these from the OSS cleanup branch before publishing:

- Private partner source modules.
- Private partner API/client tests.
- Private partner frontend affordances and import helpers.
- Private partner logos.
- Private partner entries in app catalogs, mocks, fixtures, feature text, and generated docs.

Also remove or generalize private-connector-specific entity fields from the OSS snapshot
if the public API schema would expose them:

- private external ID fields
- private app enum cases
- public OpenAPI/generated TypeScript references to private connector names

If removing the fields is too invasive for a snapshot branch, rename/generalize
them in the public branch before publishing, for example:

- private external ID fields -> private-only fields removed from public serializers
- private connected resource provider values removed from public fixtures

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

The cleanup branch must pass the private-name scan before each OSS publication.

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
