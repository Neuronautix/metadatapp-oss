<?php

declare(strict_types=1);

namespace App\Zefix\Lines;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Zefix\Lines\State\ZefixLineProvider;

#[ApiResource(
    shortName: 'ZefixLineDetail',
    formats: ['json' => ['application/json']],
    operations: [
        new Get(
            uriTemplate: '/zefix/lines/{lineID}',
            provider: ZefixLineProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixLineDetail implements ZefixLineView
{
    /**
     * @param list<ZefixLineBatchView> $batches
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $lineID,
        #[ApiProperty(readableLink: true, writableLink: false)]
        public ZefixLineSummary $line,
        public array $batches,
    ) {
    }
}
