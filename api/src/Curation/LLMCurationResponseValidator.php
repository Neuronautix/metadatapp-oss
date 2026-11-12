<?php

declare(strict_types=1);

namespace App\Curation;

use App\Enum\CurationTaskType;

final class LLMCurationResponseValidator
{
    /**
     * @return array<ValidatedCurationSuggestion>
     */
    public function validate(string $rawResponse): array
    {
        try {
            $payload = json_decode($rawResponse, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('LLM response must be valid JSON.', 0, $exception);
        }

        if (!\is_array($payload) || !isset($payload['suggestions']) || !\is_array($payload['suggestions'])) {
            throw new \InvalidArgumentException('LLM response must contain a "suggestions" array.');
        }

        $allowedRootKeys = ['suggestions'];
        $unknownRootKeys = array_diff(array_keys($payload), $allowedRootKeys);
        if ([] !== $unknownRootKeys) {
            throw new \InvalidArgumentException('LLM response contains unexpected top-level fields.');
        }

        $validatedSuggestions = [];
        foreach ($payload['suggestions'] as $index => $suggestion) {
            if (!\is_array($suggestion)) {
                throw new \InvalidArgumentException(\sprintf('Suggestion %d must be an object.', $index));
            }

            foreach (['task_type', 'field_name', 'proposed_value', 'reason'] as $requiredField) {
                if (!\array_key_exists($requiredField, $suggestion)) {
                    throw new \InvalidArgumentException(\sprintf('Suggestion %d is missing "%s".', $index, $requiredField));
                }
            }

            $taskType = CurationTaskType::tryFrom((string) $suggestion['task_type']);
            if (null === $taskType) {
                throw new \InvalidArgumentException(\sprintf('Suggestion %d has an unsupported task_type.', $index));
            }

            if (!\is_string($suggestion['field_name']) || '' === trim($suggestion['field_name'])) {
                throw new \InvalidArgumentException(\sprintf('Suggestion %d must include a non-empty field_name.', $index));
            }

            if (!\is_string($suggestion['reason']) || '' === trim($suggestion['reason'])) {
                throw new \InvalidArgumentException(\sprintf('Suggestion %d must include a non-empty reason.', $index));
            }

            $confidence = null;
            if (\array_key_exists('confidence', $suggestion) && null !== $suggestion['confidence']) {
                if (!is_numeric($suggestion['confidence'])) {
                    throw new \InvalidArgumentException(\sprintf('Suggestion %d confidence must be numeric.', $index));
                }

                $confidence = (float) $suggestion['confidence'];
                if ($confidence < 0 || $confidence > 1) {
                    throw new \InvalidArgumentException(\sprintf('Suggestion %d confidence must be between 0 and 1.', $index));
                }
            }

            $ontologyId = null;
            if (\array_key_exists('ontology_id', $suggestion) && null !== $suggestion['ontology_id']) {
                if (!\is_string($suggestion['ontology_id'])) {
                    throw new \InvalidArgumentException(\sprintf('Suggestion %d ontology_id must be a string.', $index));
                }

                $ontologyId = $suggestion['ontology_id'];
            }

            $warningCode = null;
            if (\array_key_exists('warning_code', $suggestion) && null !== $suggestion['warning_code']) {
                if (!\is_string($suggestion['warning_code'])) {
                    throw new \InvalidArgumentException(\sprintf('Suggestion %d warning_code must be a string.', $index));
                }

                $warningCode = $suggestion['warning_code'];
            }

            $providerMetadata = null;
            if (\array_key_exists('provider_metadata', $suggestion) && null !== $suggestion['provider_metadata']) {
                if (!\is_array($suggestion['provider_metadata'])) {
                    throw new \InvalidArgumentException(\sprintf('Suggestion %d provider_metadata must be an object.', $index));
                }

                $providerMetadata = $suggestion['provider_metadata'];
            }

            $validatedSuggestions[] = new ValidatedCurationSuggestion(
                taskType: $taskType,
                fieldName: trim($suggestion['field_name']),
                proposedValue: $suggestion['proposed_value'] ?? null,
                reason: trim($suggestion['reason']),
                ontologyId: $ontologyId,
                confidence: $confidence,
                warningCode: $warningCode,
                providerMetadata: $providerMetadata,
            );
        }

        return $validatedSuggestions;
    }
}
