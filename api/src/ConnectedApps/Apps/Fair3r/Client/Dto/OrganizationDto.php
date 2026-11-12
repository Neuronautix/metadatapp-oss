<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class OrganizationDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Organization ID must not be blank.')]
        public ?string $id = null,

        #[Assert\NotBlank(message: 'Organization name must not be blank.')]
        public ?string $name = null,

        public ?string $title = null,

        public ?string $type = null,

        public ?string $description = null,

        #[Assert\Url(message: "Image URL '{{ value }}' is not a valid URL.")]
        public ?string $imageUrl = null,

        #[Assert\NotNull(message: 'Organization created date must not be null.')]
        #[Assert\Type(
            type: \DateTime::class,
            message: 'created must be a valid DateTime instance.'
        )]
        public ?\DateTime $created = null,

        public ?bool $isOrganization = null,

        public ?string $approvalStatus = null,

        public ?string $state = null,
    ) {
    }
}
