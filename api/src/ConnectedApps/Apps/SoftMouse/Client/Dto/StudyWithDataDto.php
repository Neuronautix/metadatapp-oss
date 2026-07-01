<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

/**
 * This class is not in the client dto folder because it's not a direct representation of the client API
 * But for a more readable and easy to undesratnd code with create this one from merging StudyDataDto in the StudyDto.
 */
readonly class StudyWithDataDto
{
    public function __construct(
        public StudyDto $study,
        public ?StudyDataDto $studyData = null,
    ) {
    }
}
