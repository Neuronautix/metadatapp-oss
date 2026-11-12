# Metadatapp API

The API is a Symfony 7.4 and API Platform 4.3 application served by the repository Docker stack.

For repo-wide setup, commands, ownership, and agent guidance, read [../AGENTS.md](../AGENTS.md).

## Setup

From the repository root:

```bash
castor start
castor fixture
```

Use [`.env.example`](.env.example) for public-safe local environment documentation. Real credentials belong in untracked local files such as `api/.env.local` or in deployment secrets.

## Common Commands

```bash
castor migrate
castor fixture
castor cache-clear
castor phpunit
castor qa:phpstan
castor qa:cs --dry-run
castor schema-validate
```

For one-off Symfony console commands without a Castor alias:

```bash
docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console <command>'
```

## Architecture

- `src/Entity/`: Doctrine entities and API Platform resources
- `src/State/Provider/`: custom read behavior
- `src/State/Processor/`: custom write behavior
- `src/ConnectedApps/`: server-side external laboratory integrations
- `src/Curation/`: metadata curation and LLM provider abstractions
- `src/DataFixtures/Factory/`: Foundry factories for tests
- `tests/`: API, functional, and unit tests

## Connected Apps

Connected Apps logic belongs under `src/ConnectedApps/`. Commands should orchestrate services; they should not contain sync logic directly.

Default public configuration uses placeholders. Real credentials must be provided through local overrides or deployment secrets.

Read [CONNECTED_APPS.md](CONNECTED_APPS.md) before changing integration behavior.

## Curation

The review-first curation slice is available for subject records.

- Provider selection stays server-side behind `App\Curation\LLMCurationProvider`.
- The safe local default provider is `mock`.
- CurateGPT can be configured as the `curate_gpt` provider.
- Suggestions are stored separately and must be accepted or rejected before canonical updates happen.
- Provider output never mutates canonical data directly.

Endpoints:

- `POST /curation/suggest/{recordId}`
- `GET /curation/suggestions/{recordId}`
- `POST /curation/suggestions/{suggestionId}/accept`
- `POST /curation/suggestions/{suggestionId}/reject`

## Sensor Demo

The sensor demo endpoints are optional and disabled by default in the public example environment.

- `GET /demo/sensors/health`
- `GET /demo/sensors/latest`
- `GET /demo/sensors/threshold-status`

Override `SENSOR_AGENT_*` variables in `api/.env.local` only when testing against a local sensor agent.

## Testing

Backend tests use Foundry factories and database reset patterns.

```bash
castor phpunit
```

Run focused tests when possible:

```bash
castor phpunit -- tests/Api/SomeTest.php
```

Do not instantiate entities manually in API tests; use factories under `src/DataFixtures/Factory/`.
