---
description: Start the Metadatapp development environment
---

# Start Development Environment

Read `AGENTS.md` first for canonical stack and command guidance.
Use Castor as the primary entrypoint for the local stack.

## Steps

1. **Start all services**
   ```bash
   castor start
   ```

2. **Verify services are running**
   ```bash
   castor ps
   ```

3. **Check logs for any errors**
   ```bash
   castor logs
   ```

## Access Points

Once started, the following services are available:

- **API Docs**: https://metadatapp.test/docs
- **Symfony Profiler**: https://metadatapp.test/_profiler
- **Keycloak**: https://auth.metadatapp.test/oidc/
- **Osoma Frontend**: https://osoma.metadatapp.test

## Troubleshooting

If services fail to start:
- Check Docker daemon is running
- Verify `/etc/hosts` contains: `127.0.0.1 metadatapp.test osoma.metadatapp.test auth.metadatapp.test`
- Check port conflicts (80, 443, 5432)
- Review logs: `castor logs` or `castor logs <service>`
