<?php

declare(strict_types=1);

namespace App\Tests\Curation\Provider;

use App\Curation\Provider\CurateGptOntologyProvider;
use App\CurateGpt\Client\CurateGptClientInterface;
use App\CurateGpt\Client\Dto\CurateGptCandidateDto;
use App\CurateGpt\Client\Dto\CurateGptSearchRequestDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CurateGptOntologyProviderTest extends TestCase
{
    #[Test]
    public function it_merges_curategpt_candidates_into_the_review_first_suggestion_payload(): void
    {
        $client = $this->createMock(CurateGptClientInterface::class);
        $client->expects($this->once())
            ->method('search')
            ->with($this->callback(static function (CurateGptSearchRequestDto $request): bool {
                return 'mouse' === $request->query
                    && 1 === $request->limit
                    && 'ncbitaxon' === $request->collection;
            }))
            ->willReturn([
                new CurateGptCandidateDto(
                    id: 'http://purl.obolibrary.org/obo/NCBITaxon_10090',
                    label: 'Mus musculus',
                    score: 0.97,
                    metadata: ['definition' => 'house mouse'],
                ),
            ]);

        $provider = new CurateGptOntologyProvider($client);

        $raw = $provider->curate('', [
            'name' => '  Mouse 001  ',
            'species' => 'mouse',
            'sex' => 'male',
            'strain' => ' Strain Alpha ',
            'genotype' => null,
            'isPregnant' => true,
        ]);

        $payload = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        $this->assertCount(5, $payload['suggestions']);
        $speciesSuggestion = array_values(array_filter(
            $payload['suggestions'],
            static fn (array $suggestion): bool => 'species' === $suggestion['field_name']
        ))[0];

        $this->assertSame('ontology_candidate', $speciesSuggestion['task_type']);
        $this->assertSame('NCBITaxon:10090', $speciesSuggestion['ontology_id']);
        $this->assertSame('Mus musculus', $speciesSuggestion['provider_metadata']['candidateLabel']);
    }
}
