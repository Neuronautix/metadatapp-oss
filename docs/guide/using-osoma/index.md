# Using Osoma

Osoma is the React/Vite frontend you use day-to-day. This section is a complete
tour of what each part of the interface does once your instance is running.

```{toctree}
:maxdepth: 1

navigation
research-records
importing-data
fair
international-guidelines
ai-assistant
connected-apps
ai-providers
search-and-settings
```

## Signing in

Open {{ local_osoma }} and choose **Login with Keycloak**. After sign-in you land
on the **Dashboard**, which summarizes investigations, recent activity, and
integration status.

```{admonition} Your instance may show less than this guide
:class: important
Many sections are controlled by **feature flags** and by your **role**
(administrator vs. standard user), so your instance may show a subset of what is
described here. Admin-only areas are called out where relevant, and feature-gated
screens note the flag that controls them. See
[Feature flags](../administration/index.md#feature-flags).
```

## The layout

- **Left sidebar** — the main navigation, grouped into sections. See
  [Navigation](navigation).
- **Top bar** — global search (`Cmd/Ctrl + K`), the light/dark theme toggle, your
  organization and role badge, and the account menu.
- **Main panel** — the current screen.

## What's in this section

- [Navigation](navigation) — every sidebar section and what it's for.
- [Research records](research-records) — investigations, studies, subjects,
  samples, assays, datasets.
- [Importing data](importing-data) — the CSV wizard and the AI-assisted curation
  workflow.
- [FAIR & exports](fair) — FAIR scoring, the metadata catalog, and exports.
- [International guidelines reporting](international-guidelines) — ARRIVE 2.0,
  PREPARE, EQIPD & MNMS conformance with account-wide, AI-assisted filling.
- [AI Assistant](ai-assistant) — the governed, read-only assistant.
- [Connected Apps in the UI](connected-apps) — operating integrations.
- [AI Providers](ai-providers) — admin setup for the assistant.
- [Search & personal settings](search-and-settings) — global search, profile,
  appearance.
