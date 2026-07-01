---
title: "Feature: Connected Apps Integration"
type: feature
updated: 2026-06-16
source_prs: [180, 303]
related: [features/elabftw.md, features/zefix.md, decisions/backend-as-source-of-truth.md, areas/backend.md]
---

# Feature: Connected Apps Integration

## Status
Active — new enhancements for `PreclinicalTrials` integration added (PR #303).

## Summary
`Connected Apps` is a set of pre-integrated third-party tools and services that interface with the Metadatapp backend through defined APIs, enabling seamless data exchange and enrichment. The `ConnectedApps` ecosystem improves metadata quality, usability, and compliance across integrated systems.

Recent enhancements specifically improved the `PreclinicalTrials` service integration, which supports preclinical trial protocol metadata retrieval and formatting for further use.

## Key PRs (chronological)

| PR    | Date       | What changed |
|-------|------------|--------------|
| #180  | 2026-04-01 | Introduced basic Connected Apps backend architecture (including connectors for Zefix, elabFTW) |
| #303  | 2026-06-16 | Enhanced `PreclinicalTrialsService` and `ProtocolMapper` for richer metadata handling and service interoperability |

## Architecture
- **PreclinicalTrialsService:** Acts as the primary integration point for `PreclinicalTrials` protocols. Provides enriched metadata extraction and presentation capabilities.
  - Implements the `PreclinicalTrialsServiceInterface` introduced in PR #303 for greater type safety and modularity.
  - Fetches and manages connected resource links through integration with `ConnectedResourceLinkRepository`.
- **ProtocolMapper:** Handles data normalization, extraction, and formatting for `PreclinicalTrials` protocols.
  - New helper methods: `normalizeProtocol`, `cleanText`, `formatStudyCentres`, `formatStudyArms`, `buildDescription`.
  - Enhances protocol mapping with additional fields such as `goal`, `description`, `study centre`, `study arms`, and readout parameters.
  - Adjusts project name/description generation for consistency.
  - Provides an interface for generating links to protocol repositories.

## AppCode registry

| AppCode | Display name | Notes |
|---|---|---|
| `preclinicaltrials` | PreclinicalTrials.eu | Protocol metadata — public default |
| `elabftw` | ElabFTW | ELN integration — tenant ConnectedApp |
| `zefix` | Zefix | Animal care and lifecycle metadata — tenant ConnectedApp |
| `mnms` | MNMS (Minimal Neuroimaging Metadata Standards) | Public default, static curated field list |
| `guidelines_hub` | ARRIVE / PREPARE / EQIPD | Public default, static curated checklist |

## Current capabilities
- Integration with `PreclinicalTrials` system for obtaining metadata about preclinical trial protocols.
- Standardization and enhancement of metadata descriptions for protocol readability and usability.
- Improved interoperability with other components in the `ConnectedApps` ecosystem via dependency injection and uniform service interfaces.

## Known limitations & tech debt
- Potential risk of data mismatches and unexpected behavior in the `normalizeProtocol` method when processing edge cases with uncommon protocol field layouts. (See PR #303 for details.)
- The added complexity in the `ProtocolMapper` and expanded DI setup slightly increase the maintenance burden for this integration.

## Future opportunities
- Extend the `ProtocolMapper` and `PreclinicalTrialsService` to support additional protocol attributes, further enriching interconnectivity with other metadata systems and downstream reporting tools.
- Automate validation and testing for edge cases in the `normalizeProtocol` and helper methods.

## Related
- [features/elabftw.md](elabftw.md)
- [features/zefix.md](zefix.md)
- [decisions/backend-as-source-of-truth.md](../decisions/backend-as-source-of-truth.md)
- [areas/backend.md](../areas/backend.md)
