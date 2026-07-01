# AI Assistant

Metadatapp includes an optional, **governed** AI assistant for asking questions
about your data and drafting metadata suggestions. It is **disabled by default**;
an administrator turns it on and configures a provider (see
[AI Providers](ai-providers)).

- **Route:** `/assistant` (also reachable via `/ai`).

## What it can do

The assistant is a chat interface backed by **read-only** tools. It can answer
questions by querying your data and surface structured results, for example:

- *"List my studies"*, *"Show me my mice"*, *"Show the latest cages"*,
  *"List zebrafish batches"*
- *"FAIR score for my latest study"* — returns a structured FAIR assessment with
  per-criterion pass/fail cards
- Live sensor questions where the sensor integration is enabled (latest reading,
  recent history, health, threshold status)
- Help-oriented prompts like *"Explain what missing metadata means"* or *"Draft
  search filters from a free-text request"*

It recognizes intent from natural phrasing (list/show/find/latest + an entity type)
and renders results inline, often with links to the relevant screen.

## How it stays safe

The assistant follows the platform's
[governed-AI model](../introduction/concepts.md#governed-human-reviewed-ai):

- **Read-only.** Every tool call goes through a bridge that enforces read-only
  access; the assistant cannot change your data on its own.
- **Human-approved writes.** Where AI *suggests* metadata changes (for example in
  the [curation workflow](importing-data) or the subject suggestion panel), those
  are proposals you must explicitly accept.
- **Traceable.** Each AI action records the provider, model, prompt version, and —
  for accepted changes — the reviewer.

```{admonition} Preview
:class: note
The assistant is part of the public preview. Available tasks are controlled by the
operator (`AI_ENABLED_TASKS`) and the active provider; see
[Configuration](../self-hosting/configuration.md#ai--llm).
```
