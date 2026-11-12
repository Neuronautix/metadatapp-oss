<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * An Animal in SoftMouse is a Subject in MAPP.
 */
readonly class AnimalDto
{
    public function __construct(
        // Also call animalSID
        public int $guiSid,

        public ?string $altId,

        #[Assert\Date]
        public string $birthDate,

        #[Assert\DateTime(format: 'Y-m-d\TH:i:s\Z')]
        public string $createdDateTime,

        public string $strain,

        public ?string $background = null,

        public ?string $cageBarcode = null,

        public ?string $cageSid = null,

        public ?string $cageTag = null,

        public ?string $category = null,

        public ?string $comment = null,

        public ?string $creatorUser = null,

        public ?string $endReason = null,

        #[Assert\Date]
        public ?string $endDate = null,

        public ?string $endType = null,

        public ?string $generation = null,

        public ?string $genotype = null,

        #[Assert\DateTime(format: 'Y-m-d\TH:i:s\Z')]
        public ?string $lastUpdatedDateTime = null,

        public ?string $litterSid = null,

        #[Assert\Date]
        public ?string $matureDate = null,

        public ?string $notice = null,

        public ?string $ownerUser = null,

        public ?string $phenotype = null,

        public ?string $physicalTag = null,

        public ?string $protocol = null,

        #[Assert\Date]
        public ?string $procedureDate = null,

        #[Assert\Date]
        public ?string $receivedDate = null,

        #[Assert\Choice(choices: [0, 1, 2], message: 'Invalid sex value')]
        public ?int $sex = null,

        public ?string $sourceSupplier = null,

        #[Assert\Choice(choices: [0, 1, 2, 3, 4, 5, 6], message: 'Invalid state value')]
        public ?int $state = null,

        public ?string $status = null,

        public array $studyInfo = [],

        #[Assert\Date]
        public ?string $tailTagDate = null,

        #[Assert\Date]
        public ?string $weanDate = null,
    ) {
    }
}
