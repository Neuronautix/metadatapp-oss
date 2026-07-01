<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

/**
 * DTO representing an eLabFTW user.
 *
 * @see https://doc.elabftw.net/developers/rest-v2.html#/Users/get_users
 */
readonly class UserDto
{
    /**
     * @param UserTeamDto[] $teams
     */
    public function __construct(
        public int $userid,
        public string $firstname,
        public string $lastname,
        public ?string $initials,
        public string $email,
        public int $validated,
        public ?string $valid_until,
        public int $archived,
        public ?string $last_login,
        public string $fullname,
        public ?string $orcid,
        public ?string $orgid,
        public ?int $auth_service,
        public int $is_sysadmin,
        public ?int $entrypoint,
        public ?string $sig_pubkey,
        public array $teams = [],
    ) {
    }
}
