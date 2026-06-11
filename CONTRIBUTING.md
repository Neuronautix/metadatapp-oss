# Contributing to Metadatapp

Thank you for considering a contribution. Metadatapp is a public-preview research software platform, so the project values small, reviewable changes and clear validation over large rewrites.

## Before You Start

1. Read [README.md](README.md) for the project overview.
2. Read [AGENTS.md](AGENTS.md) for repository structure, commands, and agent-facing conventions.
3. Check existing issues before opening a duplicate.
4. For security issues, do not open a public issue; follow [SECURITY.md](SECURITY.md).

## Development Setup

Start the stack:

```bash
castor start
castor fixture
```

Run focused checks for the area you changed:

```bash
castor phpunit
castor qa:phpstan
castor qa:cs --dry-run
castor qa:osoma:build
castor qa:agent-docs
```

Frontend-only checks can also be run from `osoma/`:

```bash
pnpm build
pnpm run test:integration
```

## Pull Requests

- Fork the repository, create a topic branch from `main`, and open a pull request back to `main`.
- Keep PRs focused on one logical change.
- Include tests or explain why tests are not practical.
- Update docs when behavior, setup, or public APIs change.
- Do not commit secrets, private datasets, generated local artifacts, or credentials.
- If you touch API payloads consumed by Osoma, verify both producer and consumer behavior.
- Commit messages do not need a formal convention, but clear Conventional Commit-style prefixes are welcome.
- Signed-off commits are not required unless a future project policy says otherwise.

## Coding Conventions

- Backend PHP uses strict types and Symfony/API Platform patterns.
- Backend tests use Foundry factories; do not instantiate entities manually in API tests.
- Osoma feature code belongs under `osoma/src/features/`.
- Shared frontend bootstrapping belongs under `osoma/src/app/`, `osoma/src/components/`, or `osoma/src/lib/`.
- E2E tests must disable MSW with `localStorage.setItem('use_msw', 'false')`.

## License

By contributing, you agree that your contribution will be licensed under `AGPL-3.0-or-later`.
