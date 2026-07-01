# Metadatapp User & Integration Guide

Metadatapp is an open-source **metadata management and FAIR assessment platform**
for biomedical and laboratory research. It helps research teams capture, structure,
validate, connect, and export experimental metadata so their data becomes
Findable, Accessible, Interoperable, and Reusable (FAIR).

This guide is the complete manual for **everyone who interacts with Metadatapp** —
whether or not you write code.

## Find your path

::::{grid} 1 1 2 2

:::{grid-item-card} 🔭 New here?
Start with [What is Metadatapp?](introduction/what-is-metadatapp) and the
[Key concepts](introduction/concepts). No technical background needed.
:::

:::{grid-item-card} 🧪 I use the app (researcher)
Go to [Getting started](getting-started), then [Using Osoma](using-osoma/index)
for a screen-by-screen tour of every workflow.
:::

:::{grid-item-card} 🔌 I want to connect external tools
See [Connecting third-party apps](connecting-apps/index) — step-by-step for
SoftMouse, eLabFTW, FAIR3R, OSF, Tecniplast DVC, and more.
:::

:::{grid-item-card} 🛠️ I run or deploy an instance (operator)
See [Administration](administration/index) and
[Self-hosting & configuration](self-hosting/index).
:::

:::{grid-item-card} 💻 I develop or extend the code
See [For developers](for-developers/index) — architecture and how to add features.
:::

:::{grid-item-card} 📖 I need a definition
Check the [Glossary](reference/glossary) and the [FAQ & troubleshooting](reference/faq).
:::

::::

## How the pieces fit together

Metadatapp has three runtime components:

- **API** — a Symfony / API Platform backend. It owns all data and is the **only**
  component that talks to external laboratory systems and AI providers.
- **Osoma** — the React/Vite frontend. Everything in [Using Osoma](using-osoma/index)
  happens here.
- **Keycloak** — issues the OIDC tokens used to sign in.

A guiding rule shows up throughout this guide: **the browser never calls external
systems directly.** You enter credentials in Osoma, Osoma hands them to the API,
and the API stores them server-side and uses them on your behalf. This is why
connecting an app and using AI both route through backend settings.

```{admonition} Public preview
:class: note
Metadatapp is in public preview. Feature availability varies by deployment
(many areas are controlled by [feature flags](administration/index.md#feature-flags))
and some integrations are more complete than others. Where maturity matters, this
guide says so.
```

```{toctree}
:hidden:
:caption: Introduction

introduction/what-is-metadatapp
introduction/concepts
```

```{toctree}
:hidden:
:caption: Using Metadatapp

getting-started
using-osoma/index
connecting-apps/index
metadata/index
```

```{toctree}
:hidden:
:caption: Running an instance

administration/index
self-hosting/index
```

```{toctree}
:hidden:
:caption: Extending & reference

for-developers/index
reference/glossary
reference/faq
```
