<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\ProjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Project;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ProjectTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function getCollection(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        ProjectFactory::createMany(3, ['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/projects');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $this->assertJsonContains([
            '@context' => '/contexts/Project',
            '@id' => '/projects',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 3,
        ]);

        $this->assertCount(3, $response->toArray()['hydra:member']);
        $this->assertMatchesResourceCollectionJsonSchema(Project::class);
    }

    #[Test]
    public function createProject(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/projects', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'New Project',
                'description' => 'Test description',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/Project',
            '@type' => 'Project',
            'name' => 'New Project',
            'title' => 'New Project',
            'description' => 'Test description',
        ]);
    }

    #[Test]
    public function getDashboard(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/investigation/' . $project->getId() . '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'investigationId' => (string) $project->getId(),
            'animalsAtRisk' => [],
            'groupWeights' => [],
        ]);
    }

    #[Test]
    public function updateProject(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne([
            'account' => $user->getAccount(),
            'name' => 'Original Name',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $iri = $this->findIriBy(Project::class, ['name' => 'Original Name']);

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => [
                'name' => 'Updated Name',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => $iri,
            'name' => 'Updated Name',
        ]);
    }
}
