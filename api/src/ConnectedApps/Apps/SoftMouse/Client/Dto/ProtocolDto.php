<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

class ProtocolDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $title = null,
        public ?string $state = null,
        public ?string $approvalDate = null,
        public ?string $renewalDate = null,
        public ?int $approvedMice = null,
        public ?int $usedMice = null,
        public ?int $leftMice = null,
        public ?string $protocolPainCategory = null,
        public ?string $status = null,
        public ?string $primaryInvestigator = null,
        public ?string $creator = null,
        public ?bool $allowLimitBreach = null,
        public ?string $comment = null,
    ) {
    }
}
