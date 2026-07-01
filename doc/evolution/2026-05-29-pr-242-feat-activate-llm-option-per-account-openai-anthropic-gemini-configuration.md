# Evolution Report - PR #242

## Merge metadata

- Date: 2026-05-29
- PR: #242
- Title: feat: activate LLM option — per-account OpenAI/Anthropic/Gemini configuration
- Branch: copilot/activate-llm-option-in-metadatapp
- Contributors: Copilot
- Reviewer(s): dhuzard
- Merged by: dhuzard

## What was merged

### Backend
- **`Account` entity + migration**: adds `llm_provider`, `llm_api_key` (text, never returned in full), and `llm_model` nullable columns.
- **`AccountAwareModelGateway`**: new gateway alias `'account'`; reads provider and key from the current user's `Account` at request time; dispatches to OpenAI `/v1/chat/completions`, Anthropic `/v1/messages`, or Gemini `/v1beta/models/{model}:generateContent`; maps all AI task types to appropriate prompts; reports `GatewayStatus` correctly.
- **`AiProviderController`**: `GET /api/ai/provider-config` (any user) returns `{provider, keyPreview, model, configured}`; `PATCH` (admin only) updates the account's LLM config; key is masked on read (`sk-ab••••••••••••1234`).
- **`ai.yaml`**: documents `AI_MODEL_PROVIDER=account` as a valid value.

### Frontend
- **`LlmProviderPage`** at `/settings/ai-provider` (admin-guarded): status card, provider selector, password input with show/hide toggle, optional model selector, links to provider API key pages.
- **`SettingsLayout`**: "AI Provider" nav item with Bot icon.
- **MSW handler** (`aiProvider.ts`): mocks `GET/PATCH /api/ai/provider-config`.

## What it brings

- Account admins can configure their own LLM provider (OpenAI, Anthropic, or Gemini) via **Settings → AI Provider** without requiring server-side environment changes.
- Each account is isolated: their API key and provider configuration are scoped to their account only.
- Existing deployments are unaffected (default provider remains `null`; `AI_MODEL_PROVIDER` must be set to `account` to activate).

## Benefits

- User benefit: Organisations can connect their own AI model subscription to Metadatapp's AI features without sharing a centralised key.
- Product benefit: Enables a self-service AI configuration path, reducing onboarding friction for new accounts.
- Engineering benefit: The `AccountAwareModelGateway` is extensible to future providers by adding a dispatch branch; the pattern is consistent with the existing gateway architecture.
- Operational benefit: Reduces the operational burden of managing a single shared AI key at deployment level.

## Long-term vision

- Strategic theme: Multi-tenant, self-service AI integration with account-level isolation.
- Horizon impact: Medium to long term — positions Metadatapp to support a wide range of AI providers as the market evolves.
- Future opportunities unlocked: Per-user AI provider overrides, quota management, usage tracking per account.

## Risks and tradeoffs

- API keys are stored in the database; ensure the `llm_api_key` column is treated as sensitive data in backups and logs.
- The `PATCH` endpoint is admin-only but could be extended to other roles; access control must be reviewed if roles change.
- Network access to AI provider endpoints must be allowed in production; firewall rules may need updating.

## Follow-up actions

- [ ] Audit database backup and logging policies to ensure `llm_api_key` is excluded from plaintext logs (owner: ops, target: TBD)
- [ ] Add Playwright E2E test for the AI Provider settings page (owner: frontend, target: TBD)

## References

- Merge commit: f349bbdf2b3252e049dc70bdc4ed4cf41532676f
