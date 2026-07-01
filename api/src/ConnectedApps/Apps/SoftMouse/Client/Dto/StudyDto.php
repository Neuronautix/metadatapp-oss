<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

readonly class StudyDto
{
    public function __construct(
        public string $code,
        public string $title,
        public string $projectName,
        public ?string $studyType = null,
        public ?string $status = null,
        // '2022-08-04T04:00:00Z'
        public ?\DateTime $startDate = null,
        public ?\DateTime $endDate = null,
        public ?string $details = null,
        /** @var string[] $groupName */
        public array $groupName = [],
        /** @var string[] $groupDetails */
        public array $groupDetails = [],
        // '2022-08-06T03:27:54Z'
        public ?\DateTime $lastUpdatedDateTime = null,
    ) {
    }
}
