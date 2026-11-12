<?php

declare(strict_types=1);

namespace App\Zefix\Environment;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Zefix\Environment\State\ZefixEnvironmentalObservationProcessor;
use App\Zefix\Environment\State\ZefixEnvironmentalObservationProvider;

#[ApiResource(
    shortName: 'ZefixEnvironmentalObservation',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/zefix/environmental-observations',
            provider: ZefixEnvironmentalObservationProvider::class,
        ),
        new Post(
            uriTemplate: '/zefix/environmental-observations',
            processor: ZefixEnvironmentalObservationProcessor::class,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixEnvironmentalObservation
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public ?string $observationID = null,
        public ?string $scopeType = null,
        public ?string $scopeID = null,
        public ?string $systemID = null,
        public ?string $roomID = null,
        public ?string $metric = null,
        public ?float $value = null,
        public ?string $timestamp = null,
        public ?string $source = null,
        public ?string $notes = null,
        public ?string $recorder = null,
    ) {
    }
}
