<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Semantic;

use App\ConnectedApps\Reference\Semantic\EmbeddingProviderInterface;
use App\ConnectedApps\Reference\Semantic\HashingEmbeddingProvider;
use App\ConnectedApps\Reference\Semantic\VectorMath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HashingEmbeddingProviderTest extends TestCase
{
    #[Test]
    public function itProducesAFixedWidthVector(): void
    {
        $vector = (new HashingEmbeddingProvider())->embed('blood glucose level');

        self::assertCount(EmbeddingProviderInterface::DIMENSIONS, $vector);
        self::assertContainsOnly('float', $vector);
    }

    #[Test]
    public function itIsDeterministicForTheSameText(): void
    {
        $provider = new HashingEmbeddingProvider();

        self::assertSame(
            $provider->embed('open field test'),
            $provider->embed('open field test'),
        );
    }

    #[Test]
    public function itIsCaseAndWhitespaceInsensitive(): void
    {
        $provider = new HashingEmbeddingProvider();

        self::assertSame(
            $provider->embed('Body Weight'),
            $provider->embed('  body   weight  '),
        );
    }

    #[Test]
    public function itReturnsAnL2NormalizedVector(): void
    {
        $vector = (new HashingEmbeddingProvider())->embed('grip strength forelimb');

        $norm = 0.0;
        foreach ($vector as $value) {
            $norm += $value * $value;
        }

        self::assertEqualsWithDelta(1.0, sqrt($norm), 1e-9, 'A non-empty embedding must be unit length.');
    }

    #[Test]
    public function itReturnsAZeroVectorForTokenlessText(): void
    {
        $vector = (new HashingEmbeddingProvider())->embed('   ---   ');

        self::assertCount(EmbeddingProviderInterface::DIMENSIONS, $vector);
        self::assertSame(0.0, array_sum(array_map(static fn (float $v): float => abs($v), $vector)));
    }

    #[Test]
    public function similarTextsScoreHigherThanUnrelatedOnes(): void
    {
        $provider = new HashingEmbeddingProvider();

        $query = $provider->embed('blood glucose measurement');
        $related = $provider->embed('blood glucose level');
        $unrelated = $provider->embed('tail suspension immobility');

        self::assertGreaterThan(
            VectorMath::cosineSimilarity($query, $unrelated),
            VectorMath::cosineSimilarity($query, $related),
        );
    }
}
