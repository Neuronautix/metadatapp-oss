<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

final readonly class OccurrenceDto
{
    /**
     * @param array<OccurrenceDto> $occ
     */
    public function __construct(
        public array $occ,
    ) {
    }
}
