---
description: Debug issues with the Symfony profiler and logs
---

# Debug Application

Start with the smallest failing surface and use current commands only.

## Common checks

```bash
castor logs
castor logs api
castor logs osoma
castor ps
castor cache-clear
APP_DEBUG=true castor phpunit
castor schema-validate
```

## One-off Symfony debugging

Use the canonical console pattern from `AGENTS.md` for commands like:

```bash
docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console debug:list'
docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console debug:router'
docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console debug:container'
```

## Frontend checks

```bash
cd osoma
pnpm build
pnpm run test:integration
```

## Browser checks

- Use the Symfony profiler at `https://metadatapp.test/_profiler`.
- Use the browser console and network panel for Osoma issues.
- For E2E failures, confirm MSW is disabled and rerun the narrow Playwright target from `e2e/`.
