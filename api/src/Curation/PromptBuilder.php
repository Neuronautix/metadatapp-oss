<?php

declare(strict_types=1);

namespace App\Curation;

use App\Entity\Subject;

final class PromptBuilder
{
    public function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a schema-aware metadata curation assistant.
Return JSON only.
Do not write to canonical records.
Return structured suggestions for:
- missing required field detection
- value normalization
- ontology candidate suggestion
- cross-field consistency checks

If evidence is insufficient, return uncertainty with a null proposed_value instead of inventing facts.
Each suggestion must include task_type, field_name, proposed_value, reason, and may include ontology_id, confidence, and warning_code.

For subject records, the canonical field_name values are:
- name
- species
- sex
- birthAt
- strain
- genotype
- weight
- healthStatus
- isPregnant

Always use these exact camelCase field_name values in suggestions.
PROMPT;
    }

    public function buildSubjectPayload(Subject $subject): array
    {
        return [
            'recordType' => 'subject',
            'id' => (string) $subject->getId(),
            'name' => $subject->getName(),
            'species' => $subject->getSpecies()->value,
            'sex' => $subject->getSex()->value,
            'birthAt' => $subject->getBirthAt()->format(\DateTimeInterface::ATOM),
            'strain' => $subject->getStrain()->getName(),
            'genotype' => $subject->getGenotype(),
            'weight' => $subject->getWeight(),
            'healthStatus' => $subject->getHealthStatus()->value,
            'isPregnant' => $subject->getIsPregnant(),
            'ontologyCandidates' => [
                'species' => [
                    'mouse' => 'NCBITaxon:10090',
                    'rat' => 'NCBITaxon:10116',
                    'zebrafish' => 'NCBITaxon:7955',
                ],
            ],
        ];
    }
}
