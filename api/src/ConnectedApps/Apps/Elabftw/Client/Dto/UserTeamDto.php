<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

/**
 * Represents team membership of a user in eLabFTW.
 */
readonly class UserTeamDto
{
    public function __construct(
        public int $id,
        public string $name,
        public int $usergroup,
        public int $isOwner,
    ) {
    }
}
