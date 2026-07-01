<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Semantic;

use App\ConnectedApps\Reference\Semantic\HashingEmbeddingProvider;
use App\ConnectedApps\Reference\Semantic\ReferenceBestMatchService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReferenceBestMatchServiceTest extends TestCase
{
    #[Test]
    public function itRanksTheMostRelevantResultFirst(): void
    {
        $service = new ReferenceBestMatchService(new HashingEmbeddingProvider());
        $items = [
            ['id' => 'weight', 'title' => 'Body weight', 'description' => 'Total body mass'],
            ['id' => 'glucose', 'title' => 'Blood glucose', 'description' => 'Blood glucose concentration'],
        ];

        $ranked = $service->rank('glucose concentration', $items, 2);

        self::assertCount(2, $ranked);
        self::assertSame('glucose', $ranked[0]['id'], 'The glucose result must rank first for a glucose query.');
        self::assertGreaterThanOrEqual($ranked[1]['score'], $ranked[0]['score']);
        self::assertNotSame('', $ranked[0]['reason']);
    }

    #[Test]
    public function itReturnsNothingForABlankQuery(): void
    {
        $service = new ReferenceBestMatchService(new HashingEmbeddingProvider());

        self::assertSame([], $service->rank('   ', [['id' => 'a', 'title' => 'Anything']]));
    }

    #[Test]
    public function itHonoursTheLimit(): void
    {
        $service = new ReferenceBestMatchService(new HashingEmbeddingProvider());
        $items = [];
        for ($i = 0; $i < 5; ++$i) {
            $items[] = ['id' => "id-$i", 'title' => "Trait $i"];
        }

        self::assertCount(2, $service->rank('trait', $items, 2));
    }
}
