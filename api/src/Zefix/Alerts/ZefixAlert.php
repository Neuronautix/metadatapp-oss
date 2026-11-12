<?php

declare(strict_types=1);

namespace App\Zefix\Alerts;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Zefix\Alerts\State\ZefixAlertActionProcessor;
use App\Zefix\Alerts\State\ZefixAlertProvider;

#[ApiResource(
    shortName: 'ZefixAlert',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/zefix/alerts',
            provider: ZefixAlertProvider::class,
        ),
        new Post(
            uriTemplate: '/zefix/alerts/{alertID}/acknowledge',
            processor: ZefixAlertActionProcessor::class,
            read: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
        ),
        new Post(
            uriTemplate: '/zefix/alerts/{alertID}/resolve',
            processor: ZefixAlertActionProcessor::class,
            read: false,
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixAlert
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public ?string $alertID = null,
        public ?string $type = null,
        public ?string $severity = null,
        public ?string $status = null,
        public ?string $affectedEntityType = null,
        public ?string $affectedEntityId = null,
        public ?string $affectedLabel = null,
        public ?string $timestamp = null,
        public ?string $recommendedAction = null,
        public ?string $acknowledgedAt = null,
        public ?string $resolvedAt = null,
    ) {
    }
}
