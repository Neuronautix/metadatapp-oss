<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\DataTransformer;

use App\ConnectedApps\Apps\Elabftw\Dto\MouseMetadataDto;
use App\Entity\Subject;
use App\Enum\HealthStatus;
use App\Enum\Sex;
use App\Repository\CageRepository;
use App\Repository\StrainRepository;

readonly class ElabftwItemToMdtaMouseTransformer
{
    public function __construct(
        private CageRepository $cageRepository,
        private StrainRepository $strainRepository,
    ) {
    }

    public function hydrateSubjectWithExtrafields(Subject $subject, MouseMetadataDto $metadata): void
    {
        $extraFields = $metadata->extra_fields;

        if ($extraFields['ID']->value ?? null) {
            $subject->setName($extraFields['ID']->value);
        }

        if ($extraFields['DOB']->value ?? null) {
            try {
                $subject->setBirthAt(new \DateTimeImmutable($extraFields['DOB']->value));
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid date format for DOB');
            }
        }

        if ($extraFields['BW']->value ?? null) {
            $subject->setWeight((float) $extraFields['BW']->value);
        }

        if ($extraFields['Sex']->value ?? null) {
            $subject->setSex(Sex::from($extraFields['Sex']->value));
        }

        if ($extraFields['Cage']->value ?? null) {
            $cage = $this->cageRepository->find($extraFields['Cage']->value);
            if ($cage) {
                $subject->setCage($cage);
            }
        }

        if ($extraFields['Strain']->value ?? null) {
            $strain = $this->strainRepository->findOneBy(['name' => $extraFields['Strain']->value]);
            if ($strain) {
                $subject->setStrain($strain);
            }
        }

        if ($extraFields['Genotype']->value ?? null) {
            //            $genotype = $this->strainRepository->findOneBy(['name' => $extraFields['Genotype']->value]);
            $subject->setGenotype($extraFields['Genotype']->value);
        }

        if ($extraFields['HealthStatus']->value ?? null) {
            $subject->setHealthStatus(HealthStatus::tryFrom($extraFields['HealthStatus']->value) ?? HealthStatus::NORMAL);
        }
    }
}
