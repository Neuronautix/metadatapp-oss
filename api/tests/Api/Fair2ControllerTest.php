<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\ProjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class Fair2ControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function investigationFair2JsonRequiresAuthentication(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->request('GET', \sprintf('/investigations/%s/fair2.json', $project->getId()));

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function investigationFair2JsonIsAccessibleWhenAuthenticated(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', \sprintf('/investigations/%s/fair2.json', $project->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json');
    }

    #[Test]
    public function investigationFair2JsonContainsRequiredCroissantFields(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne([
            'name' => 'Neuroplasticity Study',
            'user' => $user,
            'account' => $user->getAccount(),
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/investigations/%s/fair2.json', $project->getId()));

        $this->assertResponseIsSuccessful();
        $root = $response->toArray()['@graph'][0];
        $this->assertContains('sc:Dataset', $root['@type']);
        $this->assertSame('http://mlcommons.org/croissant/1.0', $root['cr:conformsTo']);
        $this->assertSame('Neuroplasticity Study', $root['name']);
        $this->assertSame('https://creativecommons.org/licenses/by/4.0/', $root['license']);
    }

    #[Test]
    public function investigationFair2JsonContainsFair2ExtensionKeys(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/investigations/%s/fair2.json', $project->getId()));

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('fa2', $data['@context']);
        $this->assertSame('https://fair2.science/ns/', $data['@context']['fa2']);
        $this->assertArrayHasKey('cr', $data['@context']);
        $this->assertSame('http://mlcommons.org/croissant/', $data['@context']['cr']);
    }

    #[Test]
    public function investigationFair2JsonReturns404ForUnknownId(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/investigations/00000000-0000-0000-0000-000000000000/fair2.json');

        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function studyFair2JsonRequiresAuthentication(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->request('GET', \sprintf('/studies/%s/fair2.json', $experiment->getId()));

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function studyFair2JsonIsAccessibleWhenAuthenticated(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', \sprintf('/studies/%s/fair2.json', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json');
    }

    #[Test]
    public function studyFair2JsonContainsRequiredCroissantFields(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne([
            'name' => 'Cortical Mapping Assay',
            'protocol' => 'Standard cortical mapping protocol v2',
            'user' => $user,
            'account' => $user->getAccount(),
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/studies/%s/fair2.json', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $root = $response->toArray()['@graph'][0];
        $this->assertContains('sc:Dataset', $root['@type']);
        $this->assertSame('http://mlcommons.org/croissant/1.0', $root['cr:conformsTo']);
        $this->assertSame('Cortical Mapping Assay', $root['name']);
        $this->assertSame('https://creativecommons.org/licenses/by/4.0/', $root['license']);
        $this->assertSame('Standard cortical mapping protocol v2', $root['fa2:method']);
    }

    #[Test]
    public function studyFair2JsonContainsFair2ExtensionKeys(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/studies/%s/fair2.json', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('@context', $data);
        $context = $data['@context'];
        $this->assertSame('https://fair2.science/ns/', $context['fa2']);
        $this->assertSame('http://mlcommons.org/croissant/', $context['cr']);
        $this->assertSame('http://qudt.org/schema/qudt/', $context['qudt']);
        $this->assertSame('http://qudt.org/vocab/unit/', $context['unit']);
        $this->assertSame('https://credit.niso.org/', $context['credit']);
    }

    #[Test]
    public function studyFair2JsonReturns404ForUnknownId(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/studies/00000000-0000-0000-0000-000000000000/fair2.json');

        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function studyFair2JsonIncludesCreatorFromUser(): void
    {
        $user = UserFactory::createOne([
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'email' => 'jane.doe@example.com',
            'roles' => [Roles::ROLE_USER],
        ]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/studies/%s/fair2.json', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $root = $data['@graph'][0];

        $this->assertArrayHasKey('creator', $root);
        $this->assertSame('sc:Person', $root['creator']['@type']);
        $this->assertSame('Jane Doe', $root['creator']['name']);
        // Email is PII and omitted from public endpoints until an isPublic/consent flag is introduced.
        $this->assertArrayNotHasKey('email', $root['creator']);
    }

    #[Test]
    public function investigationFair2JsonIncludesLinksToStudies(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);
        ExperimentFactory::createMany(2, [
            'project' => $project,
            'user' => $user,
            'account' => $user->getAccount(),
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/investigations/%s/fair2.json', $project->getId()));

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $root = $data['@graph'][0];

        $this->assertArrayHasKey('hasPart', $root);
        $this->assertCount(2, $root['hasPart']);
        $this->assertArrayHasKey('@id', $root['hasPart'][0]);
        $this->assertCount(3, $data['@graph']);
    }

    #[Test]
    public function investigationFair2JsonForbidsAccessToOtherAccountInvestigation(): void
    {
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $owner, 'account' => $owner->getAccount()]);

        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $client = static::createClient();
        $client->loginUser($otherUser);
        $client->request('GET', sprintf('/investigations/%s/fair2.json', $project->getId()));

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function studyFair2JsonForbidsAccessToOtherAccountStudy(): void
    {
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $owner, 'account' => $owner->getAccount()]);

        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $client = static::createClient();
        $client->loginUser($otherUser);
        $client->request('GET', sprintf('/studies/%s/fair2.json', $experiment->getId()));

        $this->assertResponseStatusCodeSame(403);
    }
}
