<?php

declare(strict_types=1);

namespace App\Crosswalk\Mapping;

use App\Crosswalk\Cde\CdeCandidate;
use App\Entity\CommonDataElement;
use App\Enum\CrosswalkMappingType;

/**
 * A scored suggestion that an extracted ELN field maps to a particular CDE.
 * Deterministic and explainable: reasons and risks are human-readable strings.
 */
final readonly class MappingSuggestion
{
    /**
     * @param list<string> $reasons
     * @param list<string> $risks
     */
    public function __construct(
        public CommonDataElement|CdeCandidate $cde,
        public float $confidence,
        public array $reasons,
        public array $risks,
        public CrosswalkMappingType $mappingType,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cde' => $this->cdeRef(),
            'confidence' => $this->confidence,
            'reasons' => $this->reasons,
            'risks' => $this->risks,
            'mappingType' => $this->mappingType->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cdeRef(): array
    {
        if ($this->cde instanceof CommonDataElement) {
            return [
                'id' => $this->cde->getId()?->toRfc4122(),
                'label' => $this->cde->getLabel(),
                'localKey' => $this->cde->getLocalKey(),
                'source' => $this->cde->getSource()->value,
                'externalId' => $this->cde->getExternalId(),
            ];
        }

        return [
            'id' => null,
            'label' => $this->cde->label,
            'localKey' => $this->cde->localKey,
            'source' => $this->cde->source,
            'externalId' => $this->cde->externalId,
        ];
    }
}
