# Security Policy

## Supported Versions

Metadatapp is currently in public preview. Security fixes are prioritized for the default branch and the latest public release tag when one exists.

## Reporting a Vulnerability

Please do not report vulnerabilities in public issues.

Report suspected vulnerabilities privately through one of these channels:

- GitHub private vulnerability reporting for this repository, when available
- email `neuronautix@gmail.com`

Include:

- affected component or route
- reproduction steps
- impact assessment
- relevant logs or screenshots with secrets redacted

Neuronautix aims to acknowledge vulnerability reports within 5 business days.
Target remediation timelines depend on impact and exploitability; maintainers
will share an expected response plan with the reporter after triage.

## Security-Sensitive Areas

Pay special attention to:

- authentication and OIDC configuration
- tenant and account isolation
- Connected Apps credentials
- token display and redaction
- import/export endpoints
- AI-assisted workflows and writeback paths
- deployment and environment configuration

## Secrets

Do not commit real credentials. Use untracked local override files such as `api/.env.local` and deployment secret stores.

Historical credentials found during pre-publication audits must be treated as
burned. Rotate or revoke them in the upstream provider before relying on this
repository in a public deployment.
