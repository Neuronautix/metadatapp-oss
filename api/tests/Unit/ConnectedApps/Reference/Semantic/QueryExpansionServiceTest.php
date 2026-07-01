<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Semantic;

use App\ConnectedApps\Reference\Semantic\QueryExpansionService;
use App\Lookup\ExternalLookupService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class QueryExpansionServiceTest extends TestCase
{
    #[Test]
    public function itKeepsTheOriginalQueryFirstAndAddsSynonyms(): void
    {
        $service = $this->service(static fn (): MockResponse => self::olsResponse([
            'blood glucose level',
            'glucose measurement',
        ]));

        $terms = $service->expand('blood sugar');

        self::assertSame('blood sugar', $terms[0], 'The original query is always preserved first.');
        self::assertContains('blood glucose level', $terms);
        self::assertGreaterThan(1, \count($terms));
    }

    #[Test]
    public function itCapsTheNumberOfExpansionTerms(): void
    {
        // Every ontology returns many distinct labels; expansion must stay bounded.
        $i = 0;
        $service = $this->service(function () use (&$i): MockResponse {
            ++$i;

            return self::olsResponse(["alpha {$i}", "beta {$i}", "gamma {$i}"]);
        });

        $terms = $service->expand('locomotion');

        // 1 original + at most 4 added terms (MAX_EXPANSION_TERMS).
        self::assertLessThanOrEqual(5, \count($terms));
        self::assertSame('locomotion', $terms[0]);
    }

    #[Test]
    public function itDedupesTermsCaseInsensitively(): void
    {
        $service = $this->service(static fn (): MockResponse => self::olsResponse([
            'Blood Sugar', // same as the query, different case → dropped
            'glycaemia',
        ]));

        $terms = $service->expand('blood sugar');

        $lowered = array_map('mb_strtolower', $terms);
        self::assertSame(array_values(array_unique($lowered)), $lowered, 'No case-insensitive duplicates.');
        self::assertContains('glycaemia', $terms);
    }

    #[Test]
    public function itReturnsOnlyTheOriginalQueryWhenLookupsFail(): void
    {
        $service = $this->service(static function (): MockResponse {
            // Simulate an upstream error; expansion must swallow it and never throw.
            return new MockResponse('boom', ['http_code' => 500]);
        });

        $terms = $service->expand('open field');

        self::assertSame(['open field'], $terms);
    }

    #[Test]
    public function itReturnsEmptyForBlankQuery(): void
    {
        $service = $this->service(static fn (): MockResponse => self::olsResponse([]));

        self::assertSame([], $service->expand('   '));
    }

    private function service(callable $responseFactory): QueryExpansionService
    {
        $lookup = new ExternalLookupService(new MockHttpClient($responseFactory));

        return new QueryExpansionService($lookup, new NullLogger());
    }

    /**
     * @param list<string> $labels
     */
    private static function olsResponse(array $labels): MockResponse
    {
        $docs = array_map(static fn (string $label): array => [
            'label' => $label,
            'obo_id' => 'EFO:0000001',
            'iri' => 'http://www.ebi.ac.uk/efo/EFO_0000001',
        ], $labels);

        return new MockResponse((string) json_encode(['response' => ['docs' => $docs]]));
    }
}
