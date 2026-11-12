# Configuration reference

Metadatapp has **two configuration surfaces**:

- **API (`api/`)** — runtime environment variables read by Symfony. For local
  overrides copy [`api/.env.example`](https://github.com/Neuronautix/metadatapp-oss/blob/main/api/.env.example)
  to `api/.env.local`; in deployment supply the same variables as secrets. The
  committed `api/.env` holds **dev-only defaults**.
- **Osoma (`osoma/`)** — `VITE_*` variables that are **baked into the static
  bundle at build time**. Copy
  [`osoma/.env.example`](https://github.com/Neuronautix/metadatapp-oss/blob/main/osoma/.env.example)
  to `osoma/.env.local`.

```{admonition} Two rules that cause most surprises
:class: important
1. **`VITE_*` are compiled in at build time.** Changing them requires a **frontend
   rebuild**, not just a restart. Never put real secrets in `VITE_*` — they ship to
   the browser.
2. **Never commit production secrets.** Use your orchestrator's secret store (or
   `composer dump-env prod`). Every `change-me-dev-only` value must be replaced.
```

## Secrets you must set (never commit)

`APP_SECRET`, `OIDC_API_CLIENT_SECRET`, the credentials inside `DATABASE_URL`, all
Connected-App keys/passwords/tokens, `SENSOR_AGENT_TOKEN`, `OPENAI_API_KEY`,
`ANTHROPIC_API_KEY`, both `MERCURE_*_JWT_KEY`, and `VITE_OPS_CODE`.

## API variables

### App / core

| Variable | Controls | Example / default |
| --- | --- | --- |
| `APP_ENV` | Symfony environment | `dev` / `prod` |
| `APP_SECRET` 🔒 | App secret (signing, CSRF) | `change-me-dev-only` |
| `OPENAPI_HIDE_CONNECTED_APPS` | Hide connected-app proxy endpoints from public `/api/docs` (default hidden) | `true` |

### Database

| Variable | Controls | Example / default |
| --- | --- | --- |
| `DATABASE_URL` 🔒 | Doctrine connection DSN | `postgresql://app:app@database:5432/app?serverVersion=16&charset=utf8` |

### Identity / OIDC

| Variable | Controls | Example / default |
| --- | --- | --- |
| `OIDC_SERVER_URL` | Browser-facing realm issuer URL | `https://auth.metadatapp.test/oidc/realms/demo` |
| `OIDC_SERVER_URL_INTERNAL` | Server-to-server realm URL (reachable from the API container) | `http://keycloak:8080/oidc/realms/demo` |
| `OIDC_SWAGGER_CLIENT_ID` | Public client id for Swagger UI | `api-platform-swagger` |
| `OIDC_API_CLIENT_ID` | Confidential API client id | `api-platform-api` |
| `OIDC_API_CLIENT_SECRET` 🔒 | API client secret | `change-me-dev-only` |
| `OIDC_AUD` | Expected `aud` claim on access tokens | `api-platform` |
| `OIDC_JWK` | Public JWK to verify token signatures (dev key material only) | `{"kty":"EC",...}` |

See [Identity & Keycloak](identity) for how these must line up with the Osoma
variables — the most common deployment failure.

### CORS / hosts / proxies

| Variable | Controls | Example / default |
| --- | --- | --- |
| `TRUSTED_PROXIES` | Trusted reverse-proxy CIDRs | `127.0.0.0/8,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16` |
| `TRUSTED_HOSTS` | Host-header allowlist (regex) | `^(localhost\|php\|metadatapp-php\|metadatapp\.test)$` |
| `CORS_ALLOW_ORIGIN` | Allowed CORS origin (regex) | `^https?://(osoma\.)?metadatapp\.test$` |

### Connected Apps

These provide deployment/service-account defaults; individual users can also enter
per-integration credentials in Osoma (stored server-side, returned masked).

| Variable | Controls |
| --- | --- |
| `ELABFTW_API_URL` / `ELABFTW_API_KEY` 🔒 | eLabFTW base URL / API key |
| `SOFTMOUSE_API_URL` / `SOFTMOUSE_USERNAME` 🔒 / `SOFTMOUSE_PASSWORD` 🔒 | SoftMouse endpoint / login |
| `FAIR3R_API_URL` / `FAIR3R_API_TOKEN` 🔒 | FAIR3R endpoint / token |

### Sensor agent

| Variable | Controls | Default |
| --- | --- | --- |
| `SENSOR_AGENT_ENABLED` | Enable the sensor integration | `false` |
| `SENSOR_AGENT_BASE_URL` | Sensor agent base URL | — |
| `SENSOR_AGENT_TOKEN` 🔒 | Sensor agent bearer token | — |
| `SENSOR_AGENT_TIMEOUT` | HTTP timeout (seconds) | `5` |

### AI / LLM

| Variable | Controls | Default |
| --- | --- | --- |
| `AI_ASSISTANT_ENABLED` | Master switch for the AI assistant | `0` (off) |
| `AI_MODEL_PROVIDER` | Provider: `null`, `mock`, `curate_gpt`, `openai`, `anthropic` | `null` |
| `AI_DEFAULT_MODEL` | Default model id | `assistant-preview.v1` |
| `AI_ENABLED_TASKS` | Allowlist of AI task ids | (see `.env.example`) |
| `AI_HUMAN_APPROVAL_REQUIRED` | Require human approval for AI writes | `1` |
| `AI_WRITE_ACTIONS_ENABLED` | Allow AI write actions | `0` |
| `AI_SANDBOX_ENABLED` | Enable sandbox execution | `0` |
| `OPENAI_API_KEY` 🔒 / `OPENAI_MODEL` | OpenAI key / model | — / `gpt-5` |
| `ANTHROPIC_API_KEY` 🔒 / `ANTHROPIC_MODEL` | Anthropic key / model | — |
| `LLM_CURATION_PROVIDER` | Curation provider (`mock`, `curate_gpt`) | `mock` |
| `LLM_CURATION_MOCK_MODE` | Force mock to emit invalid output (failure testing) | `normal` |
| `CURATE_GPT_BASE_URL` | CurateGPT REST base URL | — |

The safe defaults keep AI off, read-only, and human-approved. See the
[governed-AI concept](../introduction/concepts.md#governed-human-reviewed-ai).

### Mercure / messaging

| Variable | Controls | Default |
| --- | --- | --- |
| `MESSENGER_TRANSPORT_DSN` | Symfony Messenger transport | `doctrine://default?auto_setup=0` |
| `MERCURE_URL` | Internal Mercure publish URL | `http://frontend/.well-known/mercure` |
| `MERCURE_PUBLIC_URL` | Public Mercure URL (browser subscribers) | `https://metadatapp.test/.well-known/mercure` |
| `MERCURE_PUBLISHER_JWT_KEY` 🔒 / `MERCURE_SUBSCRIBER_JWT_KEY` 🔒 | Mercure publish / subscribe JWT keys | `change-me-dev-only` |

## Osoma variables (`VITE_*`, build-time)

| Variable | Controls | Example / default |
| --- | --- | --- |
| `VITE_API_URL` | Base URL of the API | `https://metadatapp.test` |
| `VITE_SENSOR_API_URL` | Sensor agent URL (frontend) | — |
| `VITE_DOCS_URL` | Published docs URL the in-app Documentation page links to | `https://doc.metadatapp.net` |
| `VITE_DATA_MODE` | `real` (live API) or `mock` (in-browser demo data) | `real` |
| `VITE_AUTH_MODE` | `real` (Keycloak) or `bypass` (instant local session) | `real` |
| `VITE_OIDC_CLIENT_ID` | Keycloak public client id for the SPA | `osoma` |
| `VITE_OIDC_REALM` | Realm name | `demo` |
| `VITE_OIDC_SERVER_URL` | Browser-facing realm issuer (must equal API `OIDC_SERVER_URL`) | `https://auth.metadatapp.test/oidc/realms/demo` |
| `VITE_OIDC_SERVER_URL_INTERNAL` | Internal realm URL | same realm |
| `VITE_OPS_CODE` 🔒 | Optional unlock code for the `/__ops` mode page (unset disables it) | unset |

## The three environments

The same code runs in three configurations that differ mainly in **hostnames** and
**Keycloak realm**:

| Environment | Hostnames | Realm | Config source |
| --- | --- | --- | --- |
| Local development | `*.metadatapp.test` (self-signed) | `demo` | Castor variables + compose, overridable in `*.env.local` |
| Automated tests | `*.metadatapp.test` | `demo` | `api/.env.test`, `e2e/.env.example` |
| Self-hosted deployment | your domain | your realm (e.g. `users`) | deployment secrets |

🔒 = secret; never commit.
