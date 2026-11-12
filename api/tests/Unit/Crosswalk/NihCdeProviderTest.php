<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crosswalk;

use App\Crosswalk\Cde\CdeCandidate;
use App\Crosswalk\Cde\NihCdeProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NihCdeProviderTest extends TestCase
{
    #[Test]
    public function getCdeNormalizesAProviderPayloadIntoACdeCandidate(): void
    {
        $payload = [
            'tinyId' => 'ABC123',
            'version' => '2',
            'designations' => [['designation' => 'Molecular Weight']],
            'definitions' => [['definition' => 'Mass of one mole of a substance.']],
            'registrationState' => ['registrationStatus' => 'Qualified'],
            'valueDomain' => [
                'datatype' => 'Number',
                'uom' => 'g/mol',
                'permissibleValues' => [
                    ['permissibleValue' => 'low', 'valueMeaningCode' => 'C1', 'codeSystemName' => 'NCIt'],
                ],
            ],
        ];

        $client = new MockHttpClient([
            new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR), [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'application/json'],
            ]),
        ]);

        $provider = new NihCdeProvider($client, new NullLogger());

        $candidate = $provider->getCde('ABC123');

        self::assertInstanceOf(CdeCandidate::class, $candidate);
        self::assertSame('nih_cde', $candidate->source);
        self::assertSame('Molecular Weight', $candidate->label);
        self::assertSame('ABC123', $candidate->externalId);
        self::assertSame('https://cde.nlm.nih.gov/deView?tinyId=ABC123', $candidate->externalUrl);
        self::assertSame('Number', $candidate->datatype);
        self::assertSame('g/mol', $candidate->unitConstraint);
        self::assertSame('Mass of one mole of a substance.', $candidate->definition);
        self::assertNotEmpty($candidate->permissibleValues);
        self::assertSame('low', $candidate->permissibleValues[0]['value']);
        // Raw payload + provenance are preserved for lineage.
        self::assertSame($payload, $candidate->rawPayload);
        self::assertSame('nih_cde', $candidate->provenance['provider']);
    }

    #[Test]
    public function getCdeReturnsNullOnServerError(): void
    {
        $client = new MockHttpClient([
            new MockResponse('upstream exploded', ['http_code' => 500]),
        ]);

        $provider = new NihCdeProvider($client, new NullLogger());

        self::assertNull($provider->getCde('ABC123'));
    }

    #[Test]
    public function getCdeDegradesGracefullyOnTransportError(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new \RuntimeException('no connectivity');
        });

        $provider = new NihCdeProvider($client, new NullLogger());

        self::assertNull($provider->getCde('ABC123'));
    }

    #[Test]
    public function keyIsNihCde(): void
    {
        $provider = new NihCdeProvider(new MockHttpClient(), new NullLogger());

        self::assertSame('nih_cde', $provider->key());
    }
}
