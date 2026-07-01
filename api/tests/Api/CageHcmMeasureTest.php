<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\CageFactory;
use App\DataFixtures\Factory\CageHcmMeasureFactory;
use App\DataFixtures\Factory\HomeCageMonitoringFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\VariableFactory;
use App\Entity\CageHcmMeasure;
use App\Enum\HcmDataType;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CageHcmMeasureTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $cage = CageFactory::createOne(['account' => $user->getAccount()]);
        $hcm = HomeCageMonitoringFactory::createOne(['account' => $user->getAccount()]);
        $variable = VariableFactory::createOne(['account' => $user->getAccount()]);

        CageHcmMeasureFactory::createMany(3, [
            'account' => $user->getAccount(),
            'cage' => $cage,
            'homeCageMonitoring' => $hcm,
            'variable' => $variable,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/cage_hcm_measures');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/CageHcmMeasure',
            '@id' => '/cage_hcm_measures',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);

        $this->assertCount(3, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(CageHcmMeasure::class);
    }

    #[Test]
    public function createCageHcmMeasure(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $cage = CageFactory::createOne(['account' => $user->getAccount()]);
        $hcm = HomeCageMonitoringFactory::createOne(['account' => $user->getAccount()]);
        $variable = VariableFactory::createOne(['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/cage_hcm_measures', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'cage' => '/cages/' . $cage->getId(),
                'homeCageMonitoring' => '/home_cage_monitorings/' . $hcm->getId(),
                'variable' => '/variables/' . $variable->getId(),
                'value' => 123.45,
                'dataType' => '/hcm_data_types/' . HcmDataType::Activity->value,
                'recordedAt' => '2024-01-01T10:00:00+00:00',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/CageHcmMeasure',
            '@type' => 'CageHcmMeasure',
            'value' => 123.45,
            'dataType' => '/hcm_data_types/' . HcmDataType::Activity->value,
        ]);
    }

    #[Test]
    public function updateCageHcmMeasure(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $cage = CageFactory::createOne(['account' => $user->getAccount()]);
        $hcm = HomeCageMonitoringFactory::createOne(['account' => $user->getAccount()]);
        $variable = VariableFactory::createOne(['account' => $user->getAccount()]);

        $measure = CageHcmMeasureFactory::createOne([
            'account' => $user->getAccount(),
            'cage' => $cage,
            'homeCageMonitoring' => $hcm,
            'variable' => $variable,
            'value' => 100.0,
            'dataType' => HcmDataType::Activity,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(CageHcmMeasure::class, ['value' => 100.0]);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'value' => 200.0,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'value' => 200,
        ]);
    }

    #[Test]
    public function deleteCageHcmMeasure(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $cage = CageFactory::createOne(['account' => $user->getAccount()]);
        $hcm = HomeCageMonitoringFactory::createOne(['account' => $user->getAccount()]);
        $variable = VariableFactory::createOne(['account' => $user->getAccount()]);

        $measure = CageHcmMeasureFactory::createOne([
            'account' => $user->getAccount(),
            'cage' => $cage,
            'homeCageMonitoring' => $hcm,
            'variable' => $variable,
            'value' => 999.0,
            'dataType' => HcmDataType::Activity,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(CageHcmMeasure::class, ['value' => 999.0]);

        $client->request('DELETE', $iri);

        $this->assertResponseStatusCodeSame(204);
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(CageHcmMeasure::class)->findOneBy(['value' => 999.0])
        );
    }
}
