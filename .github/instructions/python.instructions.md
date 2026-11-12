---
applyTo: "**/*.{py,ipynb}"
---

# Python instructions

Read `AGENTS.md` first, then use this file as a Python-specific supplement.

## Typing
- Prefer explicit type hints for new or modified functions.
- Keep function signatures simple and stable.

## Structure
- Prefer small, composable functions.
- Avoid hidden side effects.
- Keep I/O, parsing, validation, and transformation logically separated.

## Error handling
- Reuse existing exception and logging patterns already present in the repo.
- Do not swallow exceptions silently.

## Testing
- Add focused tests around changed logic.
- Prefer narrow unit tests over broad fragile integration tests unless integration behavior is the point of the change.
