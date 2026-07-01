# AI Providers

Metadatapp includes an optional, governed **AI Assistant** that drafts metadata
suggestions. It is **disabled by default** and, when enabled, is preview-only and
human-reviewed — it never writes canonical data on its own.

## Who can configure it

Provider configuration is **admin-only**. Open the account menu (top bar) or the
sidebar **System Administration → AI Providers** (`/settings/AI-providers`).

## Configuring a provider

The page shows one card per provider (OpenAI and Anthropic). For each:

1. Enter the **API key** (a password field; it is never displayed again).
2. Pick a **model** from the suggestions or type one.
3. **Save and activate** to store the key and select the default model.
4. **Activate / Deactivate chat** controls whether that provider is used by the
   assistant.
5. **Test connection** validates the key against the provider.

A status badge shows whether each provider is *Configured* (via a stored key or
an environment variable) and whether it is *Active for chat*.

```{note}
Keys are encrypted server-side and returned only as masked hints. The runtime
prefers an active user/account-scoped key and falls back to the environment
variables (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`) when no key is saved in the UI.
```

## Provider options

The public-preview gateway recognizes these provider aliases (set by the operator
via `AI_MODEL_PROVIDER`):

| Alias | Purpose | Credentials |
| --- | --- | --- |
| `null` | Disabled (default) | none |
| `mock` | Deterministic local preview | none |
| `curate_gpt` | CurateGPT-compatible ontology curation | `CURATE_GPT_BASE_URL` + upstream secrets |
| `openai` | OpenAI gateway | AI Providers page or `OPENAI_API_KEY` |
| `anthropic` | Anthropic gateway | AI Providers page or `ANTHROPIC_API_KEY` |

For the operator-side switches (`AI_ASSISTANT_ENABLED`, `AI_MODEL_PROVIDER`,
`AI_DEFAULT_MODEL`, and the task/approval flags) see `api/.env.example` and
[`docs/CREDENTIALS.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/docs/CREDENTIALS.md).
```{warning}
Never put provider API keys in frontend code or tracked files. Direct provider
adapters live server-side under `api/src/AI/Gateway/`.
```
