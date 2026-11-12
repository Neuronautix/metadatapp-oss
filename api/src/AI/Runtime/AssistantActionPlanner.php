<?php

declare(strict_types=1);

namespace App\AI\Runtime;

final class AssistantActionPlanner
{
    private const QUERY_FILTER_KEYWORDS_PATTERN = '/\b(mouse|mice|rat|human|arabidopsis|environment|granted|restricted|withdrawn|subjects?|subject|find|show|list)\b/i';

    /**
     * @param array<string, mixed>       $input
     * @param list<array<string, mixed>> $providerInsights
     *
     * @return array<string, mixed>
     */
    public function plan(string $taskType, array $input, array $providerInsights = []): array
    {
        return match ($taskType) {
            AssistantTask::CHAT => $this->planChat($input, $providerInsights),
            AssistantTask::SUGGEST_FORM_INPUTS => $this->planFormSuggestion($input, $providerInsights),
            AssistantTask::EXPLAIN_MISSING_METADATA => $this->planMissingMetadataExplanation($input, $providerInsights),
            AssistantTask::DRAFT_QUERY_FILTERS => $this->planQueryDraft($input, $providerInsights),
            AssistantTask::GENERATE_EXPORT_CONTENT => $this->planExportContent($input, $providerInsights),
            default => [
                'content' => 'No AI task planner is available for this task.',
                'structured_output' => [],
                'suggestions' => [],
                'provenance' => $this->normalizeProvenance($providerInsights),
            ],
        };
    }

    /**
     * @param array<string, mixed>       $input
     * @param list<array<string, mixed>> $providerInsights
     *
     * @return array<string, mixed>
     */
    private function planChat(array $input, array $providerInsights): array
    {
        $messages = $this->messageList($input['messages'] ?? []);
        $lastMessage = $this->lastUserMessage($messages);
        $reply = 'I can help draft form inputs, explain missing metadata, turn natural language into filters, and prepare export-oriented structured content for review.';
        $nextActions = [
            'Suggest form inputs for an edit screen',
            'Explain missing metadata on a detail page',
            'Draft search filters from a free-text request',
            'Generate export-oriented structured content for review',
        ];

        if (str_contains($lastMessage, 'filter') || str_contains($lastMessage, 'search')) {
            $reply = 'Describe the records you want to find, and I can draft reviewable search filters that you can apply manually.';
        } elseif (str_contains($lastMessage, 'missing') || str_contains($lastMessage, 'metadata') || str_contains($lastMessage, 'field')) {
            $reply = 'Pass the entity fields you want reviewed and I will explain what is missing, why it matters, and what to check before writing anything back.';
        } elseif (str_contains($lastMessage, 'export') || str_contains($lastMessage, 'summary')) {
            $reply = 'I can prepare AI-generated export-ready structure such as an abstract, highlights, keywords, and a metadata checklist for manual review.';
        } elseif (str_contains($lastMessage, 'form') || str_contains($lastMessage, 'draft') || str_contains($lastMessage, 'suggest')) {
            $reply = 'I can suggest normalized draft values for editable form fields. Suggestions stay preview-only until you apply them.';
        }

        return [
            'content' => $reply,
            'structured_output' => [
                'next_actions' => $nextActions,
            ],
            'provenance' => $this->normalizeProvenance($providerInsights),
        ];
    }

    /**
     * @param array<string, mixed>       $input
     * @param list<array<string, mixed>> $providerInsights
     *
     * @return array<string, mixed>
     */
    private function planFormSuggestion(array $input, array $providerInsights): array
    {
        $context = isset($input['context']) && \is_array($input['context']) ? $input['context'] : [];
        $fields = isset($context['fields']) && \is_array($context['fields']) ? $context['fields'] : [];
        $resourceLabel = isset($context['resourceLabel']) && \is_string($context['resourceLabel']) ? $context['resourceLabel'] : 'record';

        $name = $this->stringValue($fields['name'] ?? $fields['label'] ?? null);
        $description = $this->stringValue($fields['description'] ?? null);
        $cohort = $this->stringValue($fields['cohort'] ?? null);
        $draftFields = [];
        $suggestions = [];

        if ('' !== $name) {
            $normalizedName = $this->normalizeWhitespace($name);
            if ($normalizedName !== $name) {
                $draftFields['name'] = $normalizedName;
                $suggestions[] = $this->fieldSuggestion('name', 'Name', $normalizedName, 'Normalize redundant whitespace for a cleaner field value.');
            }
        } elseif ('' !== $description) {
            $generatedName = ucfirst(substr($this->normalizeWhitespace($description), 0, 72));
            $draftFields['name'] = $generatedName;
            $suggestions[] = $this->fieldSuggestion('name', 'Name', $generatedName, 'Drafted a concise title from the current description.');
        }

        if ('' !== $description) {
            $normalizedDescription = $this->normalizeWhitespace($description);
            if (!str_ends_with($normalizedDescription, '.')) {
                $normalizedDescription .= '.';
            }
            if ($normalizedDescription !== $description) {
                $draftFields['description'] = $normalizedDescription;
                $suggestions[] = $this->fieldSuggestion('description', 'Description', $normalizedDescription, 'Tightened the description into a reviewable sentence.');
            }
        } elseif ('' !== $name) {
            $generatedDescription = \sprintf('Structured %s metadata prepared for controlled review and downstream export.', strtolower($this->normalizeWhitespace($name)));
            $draftFields['description'] = $generatedDescription;
            $suggestions[] = $this->fieldSuggestion('description', 'Description', $generatedDescription, 'Generated a starter description because the field is currently blank.');
        }

        if ('' !== $cohort) {
            $normalizedCohort = strtoupper($this->normalizeWhitespace($cohort));
            if ($normalizedCohort !== $cohort) {
                $draftFields['cohort'] = $normalizedCohort;
                $suggestions[] = $this->fieldSuggestion('cohort', 'Cohort', $normalizedCohort, 'Standardized the cohort label for easier filtering.');
            }
        }

        if ([] === $suggestions) {
            $suggestions[] = [
                'field' => 'review',
                'label' => 'Review',
                'preview' => 'No automatic draft fields were identified from the current form context.',
                'reason' => 'The current values already look normalized, so the best next step is curator review.',
                'value' => null,
            ];
        }

        return [
            'content' => \sprintf('Generated %d AI-reviewed draft field suggestion%s for %s.', \count($suggestions), 1 === \count($suggestions) ? '' : 's', $resourceLabel),
            'suggestions' => $suggestions,
            'structured_output' => [
                'fields' => $draftFields,
            ],
            'provenance' => $this->normalizeProvenance($providerInsights),
        ];
    }

    /**
     * @param array<string, mixed>       $input
     * @param list<array<string, mixed>> $providerInsights
     *
     * @return array<string, mixed>
     */
    private function planMissingMetadataExplanation(array $input, array $providerInsights): array
    {
        $context = isset($input['context']) && \is_array($input['context']) ? $input['context'] : [];
        $fieldDescriptors = isset($context['fields']) && \is_array($context['fields']) ? $context['fields'] : [];
        $missingFields = [];
        $suggestions = [];

        foreach ($fieldDescriptors as $descriptor) {
            if (!\is_array($descriptor)) {
                continue;
            }

            $value = $descriptor['value'] ?? null;
            $isMissing = null === $value || '' === trim((string) $value);
            if (!$isMissing) {
                continue;
            }

            $field = isset($descriptor['key']) && \is_string($descriptor['key']) ? $descriptor['key'] : 'field';
            $label = isset($descriptor['label']) && \is_string($descriptor['label']) ? $descriptor['label'] : ucfirst($field);
            $expectation = isset($descriptor['expectation']) && \is_string($descriptor['expectation']) ? $descriptor['expectation'] : 'Curator review is required before this metadata can be considered complete.';

            $missingFields[] = [
                'field' => $field,
                'label' => $label,
                'expectation' => $expectation,
            ];

            $suggestions[] = [
                'field' => $field,
                'label' => $label,
                'preview' => $expectation,
                'reason' => 'This value is missing, so the record cannot be enriched automatically.',
                'value' => null,
            ];
        }

        if ([] === $missingFields) {
            $suggestions[] = [
                'field' => 'review',
                'label' => 'Review',
                'preview' => 'No obvious missing metadata was detected in the supplied context.',
                'reason' => 'The visible fields are populated, but a curator can still review field semantics before export.',
                'value' => null,
            ];
        }

        return [
            'content' => [] === $missingFields
                ? 'No obvious missing metadata fields were detected.'
                : \sprintf('Identified %d missing metadata field%s that need curator review.', \count($missingFields), 1 === \count($missingFields) ? '' : 's'),
            'suggestions' => $suggestions,
            'structured_output' => [
                'missing_fields' => $missingFields,
            ],
            'provenance' => $this->normalizeProvenance($providerInsights),
        ];
    }

    /**
     * @param array<string, mixed>       $input
     * @param list<array<string, mixed>> $providerInsights
     *
     * @return array<string, mixed>
     */
    private function planQueryDraft(array $input, array $providerInsights): array
    {
        $context = isset($input['context']) && \is_array($input['context']) ? $input['context'] : [];
        $prompt = strtolower((string) ($input['prompt'] ?? ''));
        $filters = isset($context['filters']) && \is_array($context['filters']) ? $context['filters'] : [];

        $species = $this->matchFirst($prompt, [
            'mouse' => 'mouse',
            'mice' => 'mouse',
            'rat' => 'rat',
            'human' => 'human',
            'arabidopsis' => 'arabidopsis',
            'environment' => 'environment',
        ]);
        $consent = $this->matchFirst($prompt, [
            'granted' => 'granted',
            'restricted' => 'restricted',
            'withdrawn' => 'withdrawn',
        ]);

        $searchText = trim(preg_replace(self::QUERY_FILTER_KEYWORDS_PATTERN, '', (string) ($input['prompt'] ?? '')) ?? '');
        $draftFilters = [
            'search' => '' !== $searchText ? $searchText : ($filters['search'] ?? ''),
            'species' => $species ?? ($filters['species'] ?? ''),
            'consent' => $consent ?? ($filters['consent'] ?? ''),
        ];

        return [
            'content' => 'Drafted subject list filters from the free-text request. Review before applying.',
            'suggestions' => [
                $this->fieldSuggestion('search', 'Search', (string) $draftFilters['search'], 'Free-text query terms preserved for manual review.'),
                $this->fieldSuggestion('species', 'Species', (string) $draftFilters['species'], 'Detected a species hint from the prompt.'),
                $this->fieldSuggestion('consent', 'Consent', (string) $draftFilters['consent'], 'Detected a consent hint from the prompt.'),
            ],
            'structured_output' => [
                'filters' => $draftFilters,
            ],
            'provenance' => $this->normalizeProvenance($providerInsights),
        ];
    }

    /**
     * @param array<string, mixed>       $input
     * @param list<array<string, mixed>> $providerInsights
     *
     * @return array<string, mixed>
     */
    private function planExportContent(array $input, array $providerInsights): array
    {
        $context = isset($input['context']) && \is_array($input['context']) ? $input['context'] : [];
        $title = $this->stringValue($context['title'] ?? $context['label'] ?? $context['resourceLabel'] ?? 'Metadata record');
        $description = $this->normalizeWhitespace($this->stringValue($context['description'] ?? ''));
        $status = $this->stringValue($context['status'] ?? 'review');
        $species = $this->stringValue($context['species'] ?? '');

        $abstract = '' !== $description
            ? $description
            : \sprintf('%s prepared for governed export review with AI-generated structure requiring human approval.', $title);

        $highlights = array_values(array_filter([
            \sprintf('Resource status: %s', $status),
            '' !== $species ? \sprintf('Species context: %s', $species) : null,
            'AI output remains preview-only until manually accepted.',
        ]));

        $keywords = array_values(array_filter([
            $this->slugKeyword($title),
            $this->slugKeyword($species),
            'metadata-review',
        ]));

        $structuredOutput = [
            'title' => $title,
            'abstract' => $abstract,
            'highlights' => $highlights,
            'keywords' => $keywords,
            'metadata_checklist' => [
                'Verify identifiers and labels before export.',
                'Confirm missing metadata has curator follow-up.',
                'Review AI-generated wording before publication.',
            ],
        ];

        return [
            'content' => 'Generated export-oriented structured content for review before use.',
            'suggestions' => [
                $this->fieldSuggestion('title', 'Export title', $title, 'Prepared a reusable export title from the current entity label.'),
                $this->fieldSuggestion('abstract', 'Export abstract', $abstract, 'Prepared a structured abstract for manual review before export.'),
            ],
            'structured_output' => $structuredOutput,
            'provenance' => $this->normalizeProvenance($providerInsights),
        ];
    }

    /**
     * @param list<array<string, mixed>> $messages
     */
    private function lastUserMessage(array $messages): string
    {
        foreach (array_reverse($messages) as $message) {
            $role = $message['role'] ?? null;
            if ('user' === $role) {
                return strtolower((string) ($message['content'] ?? ''));
            }
        }

        return '';
    }

    /**
     * @param list<array<string, mixed>> $providerInsights
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeProvenance(array $providerInsights): array
    {
        return $providerInsights;
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldSuggestion(string $field, string $label, ?string $value, string $reason): array
    {
        return [
            'field' => $field,
            'label' => $label,
            'preview' => null !== $value && '' !== $value ? $value : 'n/a',
            'reason' => $reason,
            'value' => $value,
        ];
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function stringValue(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * @param array<string, string> $choices
     */
    private function matchFirst(string $prompt, array $choices): ?string
    {
        foreach ($choices as $needle => $value) {
            if (str_contains($prompt, $needle)) {
                return $value;
            }
        }

        return null;
    }

    private function slugKeyword(string $value): ?string
    {
        $normalized = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? $value, '-'));

        return '' === $normalized ? null : $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messageList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $entry): bool => \is_array($entry)));
    }
}
