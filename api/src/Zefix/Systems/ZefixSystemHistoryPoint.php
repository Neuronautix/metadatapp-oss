<?php

declare(strict_types=1);

namespace App\Zefix\Systems;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Zefix\Systems\State\ZefixSystemProvider;

#[ApiResource(
    shortName: 'ZefixSystemHistoryPoint',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/zefix/systems/{systemID}/history',
            provider: ZefixSystemProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixSystemHistoryPoint
{
    public function __construct(
        public string $timestamp,
        public float $temperature,
        public float $pH,
    ) {
    }
}
