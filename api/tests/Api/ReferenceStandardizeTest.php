<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ImportedReferenceFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Repository\CommonDataElementRepository;
use App\Security\Roles;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Covers the AI "Compare & Link / Standardize" HTTP surface. The model path is
 * unit-tested with a mocked gateway ({@see \App\Tests\Unit\ConnectedApps\Reference\Standardization\ReferenceStandardizationServiceTest});
 * here we assert the firewall, the request→service→JSON contract (via the
 * deterministic fallback so the suite stays hermetic and offline), and that
 * promotion creates a reusable {@see \App\Entity\CommonDataElement}.
 */
final class ReferenceStandardizeTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    public function testStandardizeReturnsClustersViaTheDeterministicFallback(): void
    {
        $client = static::createClient();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client->loginUser($user);

        $response = $client->request('POST', '/references/standardize', [
            'json' => ['items' => [
                ['id' => 'jax_phenome:measure:1', 'source' => 'jax_phenome', 'type' => 'measure', 'title' => 'Body weight'],
                ['id' => 'impc:measure:2', 'source' => 'impc', 'type' => 'measure', 'title' => 'Body weight'],
                ['id' => 'impc:measure:3', 'source' => 'impc', 'type' => 'measure', 'title' => 'Grip strength'],
            ]],
        ]);

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertFalse($payload['aiAvailable']);
        $this->assertNotEmpty($payload['warnings']);
        // Identical titles collapse, the distinct one stays separate.
        $this->assertCount(2, $payload['clusters']);
        $this->assertNull($payload['clusters'][0]['canonicalTerm']);
    }

    public function testPromoteCreatesACommonDataElementFromTheCanonicalTerm(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $reference = ImportedReferenceFactory::createOne([
            'account' => $user->getAccount(),
            'referenceId' => 'jax_phenome:measure:11107',
            'title' => 'Blood glucose',
            'canonicalTermIri' => 'http://purl.obolibrary.org/obo/VT_0000188',
            'canonicalTermLabel' => 'blood glucose level',
            'canonicalTermOntology' => 'vt',
            'standardizationConfidence' => 0.93,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', '/imported_references/' . $reference->getId() . '/promote-to-cde');

        $this->assertResponseStatusCodeSame(201);
        $payload = $response->toArray();
        $this->assertSame('Blood glucose', $payload['label']);

        $cdes = static::getContainer()->get(CommonDataElementRepository::class)->findBy(['account' => $user->getAccount()]);
        $this->assertCount(1, $cdes);
        $this->assertSame('Blood glucose', $cdes[0]->getLabel());
        $this->assertSame(
            'http://purl.obolibrary.org/obo/VT_0000188',
            $cdes[0]->getOntologyMappings()[0]['iri'] ?? null,
        );
    }

    public function testStandardizeRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/references/standardize', ['json' => ['items' => []]]);

        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testPromoteRejectsAnotherAccountsReference(): void
    {
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $reference = ImportedReferenceFactory::createOne(['account' => $owner->getAccount()]);
        $other = UserFactory::createOne(['roles' => [Roles::ROLE_USER], 'account' => AccountFactory::createOne()]);

        $client = static::createClient();
        $client->loginUser($other);
        $client->request('POST', '/imported_references/' . $reference->getId() . '/promote-to-cde');

        $this->assertResponseStatusCodeSame(404);
    }
}
