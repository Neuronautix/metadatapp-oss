<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

final readonly class StudyDataDto
{
    /**
     * @var TaskOccurrenceDto[]
     */
    public array $taskOccurence;

    public function __construct(
        public string $studyName,

        public int $studyId,

        public int $taskId,

        public string $taskName,

        public string $taskType,

        array $taskOccurence,
    ) {
        $this->taskOccurence = $taskOccurence;
    }
}
