# Known Limitations (public preview)

Seed list of known issues to file as GitHub issues under labels
`help wanted` / `good first issue` after the public release.

Each item below is intentionally pre-formatted as a stand-alone issue body —
copy/paste into a new issue and adjust the title.

---

## 1. Secret store is single-process (sodium-encrypted DB column)

**Labels:** `enhancement`, `security`, `infrastructure`
**Suggested title:** Wire a managed secret store adapter (Vault / AWS Secrets Manager / GCP Secret Manager)

User-entered Connected Apps and LLM provider credentials are encrypted with
libsodium and stored in PostgreSQL. The `App\Secrets\SecretStoreInterface`
abstraction (api/src/Secrets/SecretStoreInterface.php) was added so deployments
can swap the backend, but no managed-service adapter ships out of the box.

Acceptance criteria:
- Adapter for at least one of: HashiCorp Vault, AWS Secrets Manager, GCP Secret
  Manager, or Azure Key Vault.
- Documented `services_prod.yaml` override example.
- Migration path for existing sodium-encrypted records.

---

## 2. No deployment workflow ships in the public tree

**Labels:** `infrastructure`, `documentation`
**Suggested title:** Document a generic deployment recipe

Operator-specific deployment automation was removed from the public repository.
Contributors who want CD should add a generic Docker/Kubernetes recipe they can
adapt to their own infrastructure.

---

## 3. Export PDFs are line-text rendered, not branded

**Labels:** `enhancement`, `frontend`
**Suggested title:** Replace text-line PDF renderer with a branded layout (Twig + Dompdf or wkhtmltopdf)

`api/src/Service/Pdf/TextPdfRenderer.php` writes plain text via the built-in
PDF library. The FAIR/ARRIVE/experiment reports look functional but not
publication-grade. Replacing the renderer with a templated HTML→PDF path
(Dompdf, wkhtmltopdf, or Gotenberg) would let scientists hand the export
directly to reviewers.

---

## 4. CurateGPT integration depends on an external service

**Labels:** `enhancement`, `ai-curation`, `connected-apps`
**Suggested title:** Add a self-hosted fallback path for metadata curation

LLM-based curation in `api/src/Curation/` calls out to CurateGPT. There is a
mock gateway for development, but no documented self-hosted fallback. A small
adapter against a local Ollama / vLLM instance would let evaluators run the
curation flow without external API access.

---

## 5. Tenant isolation is enforced by Doctrine query extension only

**Labels:** `security`, `backend`
**Suggested title:** Add a redundant database-row-level safety check

`App\Doctrine\CurrentAccountExtension` filters every read to the current user's
account. This works for API Platform queries but a developer writing raw DQL
or a custom controller can bypass it. A row-level security policy in
PostgreSQL (or at minimum a static analyzer rule) would catch regressions.

---

## 7. Frontend feature flags rely on `localStorage`

**Labels:** `enhancement`, `frontend`
**Suggested title:** Persist feature-flag overrides server-side per user/account

Feature flag state is in `localStorage` for fast iteration during demos. For
multi-device usage, the per-user override store (already exposed through
`featurePreset`) should be the source of truth.

---

## 8. No clean-clone smoke test for Windows or macOS

**Labels:** `infrastructure`, `documentation`, `help wanted`
**Suggested title:** Verify clean-clone bootstrap on Windows + macOS runners

`.github/workflows/clean-clone-smoke.yml` covers Linux. Contributors on
Windows (WSL) and macOS occasionally hit Docker-volume / line-ending quirks
that a Linux-only smoke does not catch.
