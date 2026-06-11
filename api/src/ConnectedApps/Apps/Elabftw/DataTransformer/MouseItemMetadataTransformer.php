<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\DataTransformer;

use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExtraFieldDto;
use App\ConnectedApps\Apps\Elabftw\Dto\MouseMetadataDto;
use App\Entity\Subject;
use App\Enum\HealthStatus;

// todo improve it, use normalizer, serializer, validator
class MouseItemMetadataTransformer
{
    public static function hydrateSubjectWithExtrafields(Subject $subject, MouseMetadataDto $metadata): void
    {
    }

    /**
     * TODO check in elabftw api available keys and values ti avoid validtion error.
     *
     * @return array<string, ExtraFieldDto>
     */
    public static function transformSubjectToExtraFields(Subject $subject): array
    {
        return [
            'ID' => new ExtraFieldDto(
                type: 'text',
                value: $subject->getName(),
                description: 'Identification Number',
                required: true
            ),
            'DOB' => new ExtraFieldDto(
                type: 'date',
                value: $subject->getBirthAt()->format('Y-m-d'),
                description: 'Date of Birth',
                required: true
            ),
            'BW' => new ExtraFieldDto(
                type: 'number',
                unit: 'grams (g)',
                units: ['grams (g)'],
                value: (string) $subject->getWeight(),
                description: 'Body Weight'
            ),
            'Sex' => new ExtraFieldDto(
                type: 'select',
                value: $subject->getSex()->value,
                required: true,
                options: ['M', 'F', 'Unknown']
            ),
            'Cage' => new ExtraFieldDto(
                type: 'text',
                value: $subject->getCage()?->getId()?->toString() ?? ''
            ),
            'Strain' => new ExtraFieldDto(
                type: 'text',
                value: $subject->getStrain()->getName()
            ),
            'Genotype' => new ExtraFieldDto(
                type: 'select',
                value: $subject->getGenotype() ?? '',
                required: true,
                options: ['WT', 'KO']
            ),
            'HealthStatus' => new ExtraFieldDto(
                type: 'select',
                value: $subject->getHealthStatus()->value,
                options: array_column(HealthStatus::cases(), 'value')
            ),
        ];
    }
}
