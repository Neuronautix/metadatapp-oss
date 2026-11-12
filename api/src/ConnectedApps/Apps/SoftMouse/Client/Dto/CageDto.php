<?php

/** @noinspection ALL */

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Representation of a SoftMouse Cage (v1.2 API).
 */
readonly class CageDto
{
    public function __construct(
        public int $id,

        #[Assert\NotBlank]
        public string $ownerUser,

        #[Assert\DateTime(format: 'Y-m-d\TH:i:s\Z')]
        public \DateTimeInterface $createdDateTime,

        #[Assert\DateTime(format: 'Y-m-d\TH:i:s\Z')]
        public \DateTimeInterface $lastUpdatedDateTime,

        public ?string $creatorUser,

        public int $cageSid,

        #[Assert\Choice(choices: [0, 1, 2, 3], message: 'Invalid state value')]
        public int $state,

        public ?string $disposition = null,

        #[Assert\Date]
        public ?\DateTimeInterface $setupDate = null,

        #[Assert\Date]
        public ?\DateTimeInterface $endDate = null,

        public ?string $cageTag = null,

        public ?string $cageBarcode = null,

        public ?string $strain = null,

        public ?string $protocol = null,

        public ?string $keyContact = null,

        public ?string $status = null,

        public ?string $comment = null,

        public ?string $cageType = null,

        public ?string $perDiemRate = null,
    ) {
    }
}
