<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class FeatureFlagControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function itReturnsAccountPresetOverridesOnTheBackendRouteReachedByTheOsomaProxy(): void
    {
        $account = AccountFactory::createOne(['featurePreset' => 'zefix']);
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER], 'account' => $account]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/feature-flags/overrides');

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();

        $this->assertSame('zefix', $payload['preset']);
        $this->assertTrue($payload['enforced']);
        $this->assertNotEmpty($payload['overrides']);
        $this->assertSame('metadatapp-feat.enabled', $payload['overrides'][0]['key']);
    }
}
