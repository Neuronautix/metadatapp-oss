---
description: Sync data from connected apps
---

# Sync Connected Apps

This workflow synchronizes data from connected external applications.

## Available Connected Apps

- **SoftMouse**
- **Fair3r**
- **Elabftw**
- **Tecniplast**

## Steps

1. Start the stack with `castor start`.
2. Use the one-off console pattern from `AGENTS.md` for the specific sync command you need.

Examples:

```bash
docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console app:sync:softmouse -vvv'
docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console app:sync:fair3r -vvv'
docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console app:sync:elabftw -vvv'
docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console app:sync:tecniplast -vvv'
```

3. Run the messenger consumer only when you are explicitly validating scheduler-driven behavior:

```bash
docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console messenger:consume -vvv'
```

## Notes

- Use `-vvv` for verbose console output when you need to inspect the sync flow.
- Check `api/CONNECTED_APPS.md` for the plugin architecture before changing sync behavior.
- Keep sync logic in `api/src/ConnectedApps/`, not in controllers or UI code.
