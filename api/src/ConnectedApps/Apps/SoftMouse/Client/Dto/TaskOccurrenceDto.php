<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TaskOccurrenceDto
{
    /**
     * @var AnimalTaskDataDto[]
     */
    public array $data;

    public function __construct(
        #[Assert\NotBlank]
        public \DateTime $scheduleDate,
        array $data = [],
    ) {
        $this->data = $data;
    }
}
