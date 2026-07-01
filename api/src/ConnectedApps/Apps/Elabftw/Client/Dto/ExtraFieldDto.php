<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ExtraFieldDto
{
    public function __construct(
        /**
         * The data type of the field (e.g., "number", "text", "date", "select").
         */
        #[Assert\NotBlank(message: 'Field type is required')]
        public string $type,

        /**
         * Optional unit for numeric fields (e.g., "grams (g)").
         */
        public ?string $unit = null,

        /**
         * A list of possible units for this field (optional).
         *
         * @var string[]
         */
        #[Assert\All([new Assert\Type('string')])]
        public ?array $units = null,

        /**
         * The current value (e.g., "25", "Cage-1", "M").
         */
        #[Assert\NotBlank(message: 'Field value is required')]
        public ?string $value = null,

        /**
         * An optional human-readable description (e.g., "Body Weight").
         */
        public ?string $description = null,

        /**
         * Whether this field is required.
         */
        public ?bool $required = null,

        /**
         * For "select" fields, possible options (e.g., ["M", "F", "Unknown"]).
         *
         * @var string[]
         */
        #[Assert\All([new Assert\Type('string')])]
        public ?array $options = null,
    ) {
    }
}
