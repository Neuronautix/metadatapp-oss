# E2E Tests

This directory contains Playwright tests for Metadatapp user flows against live services.

## Setup

Start the repository stack from the root:

```bash
castor start
castor fixture
```

Install E2E dependencies:

```bash
cd e2e
npm install
```

## Environment

Use [`.env.example`](.env.example) as a public-safe starting point.

Important variables:

| Variable | Description | Default |
| --- | --- | --- |
| `E2E_BASE_URL` | Base URL for browser tests | `https://osoma.metadatapp.test` |
| `OSOMA_BASE_URL` | Osoma URL used by auth setup | `https://osoma.metadatapp.test` |
| `E2E_ADMIN_USER` | Development admin user | fixture default |
| `E2E_ADMIN_PASS` | Development admin password | fixture default |
| `E2E_USER_USER` | Development standard user | fixture default |
| `E2E_USER_PASS` | Development standard password | fixture default |

## Running Tests

```bash
npm test
npm run test:osoma
npm run test:route-health
npm run test:ui
npm run test:headed
npm run test:debug
npm run test:report
```

## Test Rules

- Tests run against live services, not MSW mocks.
- Tests must disable MSW with `localStorage.setItem('use_msw', 'false')`.
- Prefer explicit selectors, role locators, or targeted waits.
- Do not use `page.waitForNetworkIdle()`.
- Keep workers sequential when shared fixture state is involved.

## Troubleshooting

Check service state:

```bash
castor ps
castor logs
```

Self-signed development certificates are expected in local HTTPS.

## References

- [Playwright documentation](https://playwright.dev/docs/intro)
- [Metadatapp root README](../README.md)
