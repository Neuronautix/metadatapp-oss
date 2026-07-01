<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Mapper;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\CageDto;
use App\Entity\Cage;
use App\Enum\BeddingType;
use App\Enum\CageFormat;
use App\Enum\CageType;

readonly class CageMapper
{
    public function __construct(
    ) {
    }

    public function mapFromExternal(CageDto $dto, Cage $entity): void
    {
        // Types are guaranteed by method signature

        $entity
            ->setSoftmouseExternalId((string) $dto->cageSid)
            ->setType(CageType::Standard) // $dta->cageType has nothing to do with our type
            ->setFormat($this->mapCageFormat($dto))
            ->setBeddingType(BeddingType::Oak)
        ;
    }

    private function mapCageFormat(CageDto $dto): CageFormat
    {
        $mapping = [
            '5 max' => CageFormat::T1,
            'plus' => CageFormat::T2,
            'moins' => CageFormat::T3,
            'trop' => CageFormat::T4,
        ];

        return $mapping[$dto->cageType] ?? CageFormat::T1;
    }
}
