# Credential Configuration

Metadatapp is designed so the browser never calls external laboratory systems or LLM providers directly. Users enter credentials in Osoma, Osoma sends them to the Symfony API, and backend services use them when synchronizing data or invoking approved AI workflows.

## Connected Apps

Connected App credentials are account/user-scoped records on `ConnectedApp`.

Users configure them from:

```text
Osoma -> Connected Applications -> integration detail -> Edit Settings
```

Supported credential shapes:

| App | Required user input |
| --- | --- |
| SoftMouse | `username`, `password`, external URL |
| eLabFTW | `apiKey`, external URL |
| Fair3R | `accessToken`, external URL |
| Tecniplast DVC | `accessToken`, optional `refreshToken`, external URL |
| protocols.io | `accessToken`, external URL |

The API accepts these values through `authenticationParameters` and returns only masked `authenticationParameterHints`. Legacy integrations can also use the top-level `token` field, but new UI and backend code should prefer `authenticationParameters`.

Blank credential fields in the UI mean "keep the existing stored value." Partial PATCH updates merge with existing `authenticationParameters` so a user can rotate one secret without re-entering every field.

## Local Development Defaults

Tracked `.env` files contain placeholders only. To use a real external app locally, create `api/.env.local` and override the relevant URL or default token:

```bash
ELABFTW_API_URL=https://your-elabftw.example/api/v2/
ELABFTW_API_KEY=replace-me
FAIR3R_API_URL=https://validation.fair3r.fr
FAIR3R_API_TOKEN=replace-me
```

Prefer the in-app Connected Apps settings for per-user credentials. Use environment variables for local bootstrapping, tests, service accounts, and deployments that intentionally run with shared credentials.

## LLM Providers

The AI assistant is governed by `AI_ASSISTANT_ENABLED`, `AI_MODEL_PROVIDER`, and `AI_DEFAULT_MODEL`. Admin users can configure provider keys from:

```text
Osoma -> Settings -> AI Providers
```

Saved provider keys are encrypted server-side and returned only as masked hints. Runtime gateways prefer the active user-scoped provider key and fall back to environment variables when no user key is configured.

The public-preview gateway supports:

| Provider alias | Purpose | Credential path |
| --- | --- | --- |
| `null` | Disabled/default mode | No credentials |
| `mock` | Deterministic local AI preview | No credentials |
| `curate_gpt` | CurateGPT-compatible ontology curation backend | `CURATE_GPT_BASE_URL` and any upstream deployment secrets required by that service |
| `openai` | OpenAI Responses API gateway | Settings -> AI Providers or `OPENAI_API_KEY` |
| `anthropic` | Anthropic Messages API gateway | Settings -> AI Providers or `ANTHROPIC_API_KEY` |

Do not put provider API keys in frontend code or tracked files. Direct provider adapters live server-side under `api/src/AI/Gateway/`.

## Release Requirements

Before a public tagged release that advertises full external provider support:

- Connected Apps must keep accepting user-entered credentials through Osoma.
- LLM provider adapters must support user- or account-scoped API keys without exposing raw keys after save.
- Secret storage must be backed by deployment-grade encryption or a managed secret store.
- Tests must cover credential masking, partial credential rotation, and tenant/account isolation.
