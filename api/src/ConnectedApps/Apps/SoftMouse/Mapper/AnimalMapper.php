<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Mapper;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\AnimalDto;
use App\Entity\Cage;
use App\Entity\Strain;
use App\Entity\Subject;
use App\Enum\BeddingType;
use App\Enum\CageFormat;
use App\Enum\CageType;
use App\Enum\HealthStatus;
use App\Enum\Sex;
use App\Enum\Species;
use App\Repository\CageRepository;
use App\Repository\StrainRepository;
use Random\RandomException;

// This class should only handle the mapping of the animal to the subject, data conversion and validation. But not the creation of the subject and evenless the cage and strain.
// this class should be used in a SoftMouseConverter or service, or transformer or translator ? where we will map all linked entities and handle the get or create or update function
readonly class AnimalMapper
{
    public function __construct(
        private CageRepository $cageRepository,
        private StrainRepository $strainRepository,
    ) {
    }

    /**
     * @throws \DateMalformedStringException
     * @throws RandomException
     */
    public function mapFromDto(AnimalDto $dto, Subject $subject, bool $forceCreateStrain = true, bool $forceCreateCage = true): void
    {
        $strain = $this->strainRepository->findOneBy(['name' => $dto->strain]);
        if (null === $strain) {
            if ($forceCreateStrain) {
                $strain = new Strain();
                $strain->setName($dto->strain);
            } else {
                throw new \LogicException('Strain not found: ' . $dto->strain);
            }
        }

        $cage = $this->cageRepository->findOneBy(['softmouseExternalId' => $dto->cageSid]);
        if (null === $cage && $forceCreateCage) {
            $cage = new Cage();
            $cage->setSoftmouseExternalId($dto->cageSid);
            $cage->setType(CageType::Standard);
            $cage->setFormat(CageFormat::T1);
            $cage->setHasEnrichments(false);
            $cage->setBeddingType(BeddingType::Oak);
        }

        $defaultTempWeight = random_int(200, 300) / 10;
        $defaultHealthStatus = HealthStatus::NORMAL;
        $subject
            ->setSoftmouseExternalId((string) $dto->guiSid)
            ->setName($this->transformName($dto))
            ->setSex($this->mapSex($dto->sex))
            ->setBirthAt(new \DateTime($dto->birthDate))
            ->setStrain($strain)
            ->setCage($cage)
            ->setSpecies(Species::Mouse)
            ->setBirthAt(new \DateTime($dto->birthDate))
            ->setWeight($defaultTempWeight)
            ->setHealthStatus($defaultHealthStatus)
            ->setGenotype($dto->genotype)
        ;
    }

    protected function mapSex(?int $dtoSex): Sex
    {
        if (null === $dtoSex) {
            return Sex::Unknown;
        }
        $mappedSex = [
            0 => Sex::Female,
            1 => Sex::Male,
            2 => Sex::Unknown,
        ];

        return $mappedSex[$dtoSex];
    }

    /** Temporary behavior: generate a prefixed name until SoftMouse provides one. */
    protected function transformName(AnimalDto $dto): string
    {
        return 'SM-' . $dto->physicalTag;
    }
}
