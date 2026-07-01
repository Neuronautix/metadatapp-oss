<?php

declare(strict_types=1);

namespace App\Guideline;

use App\AI\Gateway\ModelGatewayRegistry;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Runtime\AssistantTask;
use App\AI\Runtime\AssistantTaskMapper;
use App\Guideline\Evidence\EvidenceBundle;
use App\Guideline\Evidence\EvidenceItem;
use App\Guideline\Schema\GuidelineTemplate;
use App\Guideline\Schema\RequiredMetadata;
use Psr\Log\LoggerInterface;

/**
 * The LLM fill channel (§7.3). Drafts a grounded candidate value for a single
 * guideline field by invoking the shared model gateway via the existing AI
 * stack (ModelGatewayRegistry + AssistantTaskMapper) — it does NOT write a new
 * HTTP client.
 *
 * Degrades gracefully: with the assistant disabled, the task not enabled, or no
 * configured gateway (no API key), it returns a clear "ai unavailable" draft
 * rather than crashing. Never persists — the user reviews then saves.
 */
final readonly class GuidelineAiFiller
{
    public function __construct(
        private AiFeatureFlags $featureFlags,
        private AssistantTaskMapper $taskMapper,
        private ModelGatewayRegistry $gatewayRegistry,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata the shared metadata dict (for context values)
     * @param ?EvidenceBundle      $bundle   deterministic, prompt-safe grounding evidence
     */
    public function draft(GuidelineTemplate $template, RequiredMetadata $field, array $metadata, ?EvidenceBundle $bundle = null): GuidelineFieldDraft
    {
        $evidence = null !== $bundle ? $bundle->forField($field) : [];
        $suggestions = null !== $bundle ? $bundle->suggestionsForField($field) : [];

        if (!$this->featureFlags->isAssistantEnabled()) {
            return GuidelineFieldDraft::unavailable($field->id, 'AI assistant is disabled; fill this field manually.', $suggestions);
        }
        if (!$this->featureFlags->allowsTask(AssistantTask::DRAFT_GUIDELINE_FIELD)) {
            return GuidelineFieldDraft::unavailable($field->id, 'AI guideline drafting is not enabled; fill this field manually.', $suggestions);
        }
        if (!$this->gatewayRegistry->hasConfiguredGateway()) {
            return GuidelineFieldDraft::unavailable($field->id, 'No AI provider is configured (no API key); fill this field manually.', $suggestions);
        }

        $payload = $this->buildPayload($template, $field, $metadata, $evidence);
        $mapped = $this->taskMapper->mapDraftGuidelineField($payload);

        try {
            $response = $this->gatewayRegistry->getDefaultGateway()->invoke($mapped->request);
        } catch (\Throwable $e) {
            $this->logger->warning('Guideline AI draft failed', [
                'field_id' => $field->id,
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);

            return GuidelineFieldDraft::unavailable($field->id, 'AI draft is temporarily unavailable; fill this field manually.', $suggestions);
        }

        if (!$response->available) {
            return GuidelineFieldDraft::unavailable($field->id, '' !== $response->message ? $response->message : 'AI provider unavailable.', $suggestions);
        }

        $structured = isset($response->output['structured_output']) && \is_array($response->output['structured_output'])
            ? $response->output['structured_output']
            : [];

        $value = isset($structured['value']) && \is_scalar($structured['value']) ? (string) $structured['value'] : '';
        $rationale = isset($structured['rationale']) && \is_scalar($structured['rationale']) ? (string) $structured['rationale'] : '';

        return new GuidelineFieldDraft(
            fieldId: $field->id,
            value: $value,
            rationale: $rationale,
            available: true,
            provider: $response->provider,
            model: $response->model,
            message: $response->message,
            usedEvidence: $evidence,
            suggestions: $suggestions,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<EvidenceItem>   $evidence
     *
     * @return array<string, mixed>
     */
    private function buildPayload(GuidelineTemplate $template, RequiredMetadata $field, array $metadata, array $evidence): array
    {
        $contextValues = [];
        foreach ($field->crosswalk as $key) {
            if (isset($metadata[$key]) && $this->isNonEmpty($metadata[$key])) {
                $contextValues[$key] = $metadata[$key];
            }
        }

        return [
            'template' => $template->name,
            'template_id' => $template->id,
            'field' => [
                'field_id' => $field->id,
                'label' => $this->labelFor($field),
                'arrive_section' => $field->arriveSection,
                'prepare_section' => $field->prepareSection,
                'eqipd_section' => $field->eqipdSection,
                'severity' => $field->severity,
                'prompt' => $field->prompt,
                'guidance' => $field->guidance,
                'context_keys' => $field->crosswalk,
                'context_values' => $contextValues,
            ],
            'evidence' => array_map(static fn (EvidenceItem $i): array => $i->toPromptArray(), $evidence),
        ];
    }

    private function labelFor(RequiredMetadata $field): string
    {
        return ucwords(str_replace(['prepare_', 'eqipd_', '_'], ['', '', ' '], $field->id));
    }

    private function isNonEmpty(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }
        if (\is_string($value)) {
            return '' !== trim($value);
        }
        if (\is_array($value)) {
            return [] !== $value;
        }

        return true;
    }
}
