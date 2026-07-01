---
description: Create a new API entity with API Platform
---

# Create New Entity

Use `AGENTS.md` plus `.agent/rules/php-backend.md` as the primary references.

## Steps

1. Start from a nearby entity such as `api/src/Entity/Experiment.php` or `Project.php`.
2. Add the entity/resource under `api/src/Entity/` using the same UUID, security, and API Platform patterns already used in neighboring files.
3. Add or update the matching Foundry factory in `api/src/DataFixtures/Factory/`.
4. If the persistence model changes, generate a migration with:
   ```bash
   docker compose -p metadatapp --profile default -f infrastructure/docker/docker-compose.yml exec api sh -lc 'cd /var/www/api && bin/console doctrine:migrations:diff'
   ```
5. Add API or functional coverage in `api/tests/` using `ApiTestCase`, `Factories`, and `ResetDatabase`.
6. Verify with the narrowest relevant commands first:
   ```bash
   castor phpunit -- tests/Api/YourEntityTest.php
   castor qa:phpstan
   ```

## Rules

- Do not instantiate entities manually in tests.
- Keep custom API behavior in state providers/processors instead of controllers.
- If the entity participates in Connected Apps or tenancy, reuse the existing interfaces and patterns already present in nearby entities.
