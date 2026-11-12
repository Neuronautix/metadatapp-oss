---
description: Run all quality checks and tests
---

# Run Quality Checks

Use the smallest set of checks that covers the changed surface, then widen only if needed.

## Backend baseline

```bash
castor qa:all
castor qa:cs --dry-run
castor qa:phpstan
castor qa:agent-docs
castor phpunit
castor schema-validate
```

## Osoma checks

```bash
castor qa:osoma:build

cd osoma
pnpm run test:integration
pnpm run openapi:types:dvc-proxy
```

## Browser checks

Run Playwright when the change affects end-user flows, auth, or API/UI boundaries:

```bash
cd e2e
npm test
```

## Notes

- Prefer narrow verification first, for example `castor phpunit -- tests/Api/SpecificTest.php`.
- Use `AGENTS.md` as the command source of truth if this file and another workflow ever diverge.
