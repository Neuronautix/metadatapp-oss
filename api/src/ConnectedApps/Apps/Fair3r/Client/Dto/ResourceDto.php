<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ResourceDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Resource ID must not be blank.')]
        public string $id,

        #[Assert\Type(
            type: \DateTime::class,
            message: 'cacheLastUpdated must be a valid DateTime.'
        )]
        public ?\DateTime $cacheLastUpdated = null,

        #[Assert\Url(message: "Cache URL '{{ value }}' is not a valid URL.")]
        public ?string $cacheUrl = null,

        #[Assert\NotNull(message: 'Resource created date must not be null.')]
        #[Assert\Type(
            type: \DateTime::class,
            message: 'created must be a valid DateTime.'
        )]
        public ?\DateTime $created = null,

        public bool $datastoreActive = false,

        public bool $datastoreContainsAllRecordsOfSourceFile = false,

        public ?string $description = null,

        public ?string $format = null,

        public ?string $hash = null,

        #[Assert\Type(
            type: \DateTime::class,
            message: 'lastModified must be a valid DateTime.'
        )]
        public ?\DateTime $lastModified = null,

        #[Assert\NotNull(message: 'metadataModified must not be null.')]
        #[Assert\Type(
            type: \DateTime::class,
            message: 'metadataModified must be a valid DateTime.'
        )]
        public ?\DateTime $metadataModified = null,

        public ?string $mimetype = null,

        public ?string $mimetypeInner = null,

        public ?string $name = null,

        #[Assert\NotBlank(message: 'packageId must not be blank.')]
        public ?string $packageId = null,

        public ?int $position = null,

        public ?string $resourceType = null,

        public ?int $size = null,

        public ?string $state = null,

        #[Assert\Url(message: "Resource URL '{{ value }}' is not a valid URL.")]
        public ?string $url = null,

        public ?string $urlType = null,
    ) {
    }
}
