---
description: Reset database and load fresh fixtures
---

# Reset Database

Use this when you need a clean local dataset without changing repo-tracked files.

## Steps

1. **Run migrations**
   ```bash
   castor migrate
   ```

2. **Load fixtures**
   ```bash
   castor fixture
   ```

3. **Verify schema**
   ```bash
   castor schema-validate
   ```

## Notes

- `castor fixture` reloads the development fixtures; use it only when replacing your current local data is acceptable.
- If you need a one-off Doctrine command without a Castor alias, use the `docker compose ... exec api ...` pattern from `AGENTS.md`.
