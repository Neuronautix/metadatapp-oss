<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class DatasetDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Author must not be blank.')]
        public ?string $author = null,

        #[Assert\Email(message: "Author email '{{ value }}' is not a valid email.")]
        public ?string $authorEmail = null,

        #[Assert\NotBlank(message: 'Creator user ID must not be blank.')]
        public ?string $creatorUserId = null,

        #[Assert\NotBlank(message: 'Dataset ID must not be blank.')]
        public ?string $id = null,

        public bool $isOpen = false,

        public ?string $licenseId = null,

        public ?string $licenseTitle = null,

        public ?string $maintainer = null,

        #[Assert\Email(message: "Maintainer email '{{ value }}' is not a valid email.")]
        public ?string $maintainerEmail = null,

        #[Assert\NotNull(message: 'metadataCreated must not be null.')]
        #[Assert\Type(
            type: \DateTime::class,
            message: 'metadataCreated must be a valid DateTime instance.'
        )]
        public ?\DateTime $metadataCreated = null,

        #[Assert\NotNull(message: 'metadataModified must not be null.')]
        #[Assert\Type(
            type: \DateTime::class,
            message: 'metadataModified must be a valid DateTime instance.'
        )]
        public ?\DateTime $metadataModified = null,

        #[Assert\NotBlank(message: 'Name must not be blank.')]
        public ?string $name = null,

        public ?string $notes = null,

        public ?int $numResources = null,

        public ?int $numTags = null,

        public ?array $organization = null,

        #[Assert\NotBlank(message: 'Owner organization must not be blank.')]
        public ?string $ownerOrg = null,

        public bool $private = false,

        public ?string $state = null,

        #[Assert\NotBlank(message: 'Title must not be blank.')]
        public ?string $title = null,

        public ?string $type = null,

        #[Assert\Url(message: "The URL '{{ value }}' is not valid.")]
        public ?string $url = null,

        public ?string $version = null,

        /**
         * @var ResourceDto[]|null
         */
        #[Assert\Valid]
        #[Assert\All([
            new Assert\Type(type: ResourceDto::class, message: 'Each resource must be an instance of ResourceDto.'),
        ])]
        public ?array $resources = null,

        /**
         * @var array<string> $tags
         */
        public ?array $tags = null,

        /**
         * @var DatastoreFieldDto[]
         */
        #[Assert\Valid]
        #[Assert\All([
            new Assert\Type(type: DatastoreFieldDto::class),
        ])]
        public ?array $extras = null,

        public ?array $groups = null,

        public ?array $relationshipsAsSubject = null,

        public ?array $relationshipsAsObject = null,

        public ?string $doi = null,

        public ?bool $doiStatus = null,

        #[Assert\Url(message: "The domain '{{ value }}' is not a valid URL.")]
        public ?string $domain = null,

        #[Assert\Type(
            type: \DateTime::class,
            message: 'doiDatePublished must be a valid DateTime instance.'
        )]
        public ?\DateTime $doiDatePublished = null,

        public ?string $doiPublisher = null,
    ) {
    }
}
