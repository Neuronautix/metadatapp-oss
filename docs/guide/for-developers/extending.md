# Extending Metadatapp

Two common, end-to-end recipes. These are deliberately *task-shaped*; for the
conventions behind each step, follow the canonical sources linked inline and copy
the nearest existing example in the codebase.

```{admonition} Golden rule
:class: important
Match the surrounding code. Reuse existing entities, providers, and factories as
templates rather than inventing new patterns —
[`AGENTS.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/AGENTS.md) is the
authoritative playbook and wins any conflict.
```

## Recipe: add an API resource (entity) end-to-end

Goal: expose a new tenant-scoped resource through the API and surface it in Osoma.

1. **Create the entity** under `api/src/Entity/`. Start from an existing one
   (`Experiment.php`, `Project.php`) so you inherit the conventions:
   - `declare(strict_types=1);` at the top.
   - `#[ApiResource]` plus Doctrine attributes.
   - A UUID identifier via `UuidGenerator`.
   - Implement `AccountAwareInterface` (and `UserAwareInterface` if user-owned) so
     the resource is tenant-scoped.
2. **Custom read/write behavior** (only if needed) goes in
   `api/src/State/Provider/` and `api/src/State/Processor/` — not controllers. On
   create, the `SetAccountProcessor` / `SetUserProcessor` decorators attach the
   current tenant automatically.
3. **Generate and review a migration**:
   ```bash
   docker compose -p metadatapp --profile default \
     -f infrastructure/docker/docker-compose.yml exec api \
     sh -lc 'cd /var/www/api && bin/console doctrine:migrations:diff'
   castor migrate
   ```
4. **Add a test** under `api/tests/`: extend `ApiTestCase`, use a Foundry factory
   from `api/src/DataFixtures/Factory/` (add one for the new entity), and the
   `ResetDatabase` trait. Do not instantiate entities by hand in tests.
5. **Surface it in Osoma**: add a feature under `osoma/src/features/`, fetch with
   `@tanstack/react-query` via the `apiFetch` helper in `osoma/src/lib/api.ts`, and
   add a route. The generic Metadata Catalog (`/metadata`) will also pick up any
   `#[ApiResource]` automatically.
6. **Verify**:
   ```bash
   castor phpunit
   castor qa:phpstan
   castor schema-validate
   castor qa:osoma:build
   ```

See [`ARCHITECTURE.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/ARCHITECTURE.md)
(backend layering) and
[`api/README.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/api/README.md).

## Recipe: add a Connected App

Goal: integrate a new external system as a server-side plugin. Connected Apps
follow a uniform contract — none is hard-coded into the core. Read
[`api/CONNECTED_APPS.md`](https://github.com/Neuronautix/metadatapp-oss/blob/main/api/CONNECTED_APPS.md)
first.

1. **Register the code**: add a case to the `AppCode` enum
   (`api/src/Enum/AppCode.php`), e.g. `case MyLab = 'mylab';`.
2. **Create the app package** under `api/src/ConnectedApps/Apps/MyLab/`, mirroring an
   existing adapter (eLabFTW is the most complete):
   ```text
   Apps/MyLab/
     Client/         HTTP client (+ optional Mock/ for tests)
     Service/        Domain orchestration; implements ConnectedAppServiceInterface
     State/          Provider/Processor for sync resources
     Synchronizer/   Per-entity sync logic (Subject, Experiment, ...)
     DataTransformer/  external DTO ↔ entity mapping
   ```
3. **Make the service resolvable**: implement `ConnectedAppServiceInterface` and
   return `true` from `supportsCode()` for your new `AppCode`. The
   `ConnectedAppServiceFactory` discovers it via the tagged iterator — no central
   registration needed.
4. **Wire credentials**: surface the fields in the Osoma config dialog
   (`osoma/src/features/integrations/connected-apps/components/ConnectedAppConfigDialog.tsx`).
   Credentials are stored server-side and returned masked — never call the external
   system from the frontend.
5. **Document it** (required): add `docs/guide/connecting-apps/mylab.md` and list it
   in `docs/guide/connecting-apps/index.md`. Use
   [`elabftw.md`](../connecting-apps/elabftw) as the template.
6. **Verify** the docs gate passes (it fails if an `AppCode` has no page):
   ```bash
   castor qa:docs-check
   ```

```{tip}
The `castor qa:docs-check` gate exists precisely so a new integration can't ship
without user-facing documentation. It runs in CI via `.github/workflows/docs.yml`.
```
