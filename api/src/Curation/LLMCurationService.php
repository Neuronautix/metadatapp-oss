<?php

declare(strict_types=1);

namespace App\Curation;

use App\Entity\CurationSuggestion;
use App\Entity\Subject;
use App\Enum\CurationSuggestionStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class LLMCurationService
{
    public function __construct(
        private LLMCurationProviderRegistry $providerRegistry,
        private PromptBuilder $promptBuilder,
        private LLMCurationResponseValidator $responseValidator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<CurationSuggestion>
     */
    public function requestSubjectSuggestions(Subject $subject): array
    {
        $providerName = $this->providerRegistry->getDefaultProviderName();
        $provider = $this->providerRegistry->getDefaultProvider();
        $rawResponse = $provider->curate(
            $this->promptBuilder->buildSystemPrompt(),
            $this->promptBuilder->buildSubjectPayload($subject),
        );

        try {
            $validatedSuggestions = $this->responseValidator->validate($rawResponse);
        } catch (\InvalidArgumentException $exception) {
            $failedSuggestion = (new CurationSuggestion())
                ->setSubject($subject)
                ->setTaskType(\App\Enum\CurationTaskType::MissingRequiredField)
                ->setStatus(CurationSuggestionStatus::Failed)
                ->setProviderName($providerName)
                ->setFieldName('record')
                ->setCurrentValue(null)
                ->setProposedValue(null)
                ->setReason('LLM response rejected: ' . $exception->getMessage())
                ->setWarningCode('invalid_llm_output')
                ->setRawLlmResponse($rawResponse)
                ->setFailureDetails([
                    'error' => $exception->getMessage(),
                ])
            ;

            $this->entityManager->persist($failedSuggestion);
            $this->entityManager->flush();

            return [$failedSuggestion];
        }

        $persistedSuggestions = [];
        foreach ($validatedSuggestions as $validatedSuggestion) {
            $fieldName = $this->normalizeFieldName($validatedSuggestion->fieldName);
            $suggestion = (new CurationSuggestion())
                ->setSubject($subject)
                ->setTaskType($validatedSuggestion->taskType)
                ->setStatus(CurationSuggestionStatus::PendingReview)
                ->setProviderName($providerName)
                ->setFieldName($fieldName)
                ->setCurrentValue($this->resolveCurrentFieldValue($subject, $fieldName))
                ->setProposedValue($validatedSuggestion->proposedValue)
                ->setOntologyId($validatedSuggestion->ontologyId)
                ->setConfidence($validatedSuggestion->confidence)
                ->setReason($validatedSuggestion->reason)
                ->setWarningCode($validatedSuggestion->warningCode)
                ->setProviderMetadata($validatedSuggestion->providerMetadata)
                ->setRawLlmResponse($rawResponse)
            ;

            $this->entityManager->persist($suggestion);
            $persistedSuggestions[] = $suggestion;
        }

        $this->entityManager->flush();

        return $persistedSuggestions;
    }

    private function resolveCurrentFieldValue(Subject $subject, string $fieldName): mixed
    {
        return match ($this->normalizeFieldName($fieldName)) {
            'name' => $subject->getName(),
            'species' => $subject->getSpecies()->value,
            'sex' => $subject->getSex()->value,
            'birthAt' => $subject->getBirthAt()->format(\DateTimeInterface::ATOM),
            'strain' => $subject->getStrain()->getName(),
            'genotype' => $subject->getGenotype(),
            'weight' => $subject->getWeight(),
            'healthStatus' => $subject->getHealthStatus()->value,
            'isPregnant' => $subject->getIsPregnant(),
            default => null,
        };
    }

    private function normalizeFieldName(string $fieldName): string
    {
        return match ($fieldName) {
            'birth_at' => 'birthAt',
            'health_status' => 'healthStatus',
            'is_pregnant' => 'isPregnant',
            default => $fieldName,
        };
    }
}
