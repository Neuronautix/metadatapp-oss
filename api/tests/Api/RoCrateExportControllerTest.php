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

final class RoCrateExportControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function roCrateExportRequiresAuthentication(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        static::createClient()->request('GET', \sprintf('/api/v1/export/ro-crate/%s', $project->getId()));

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function roCrateExportForbidsOtherAccountInvestigation(): void
    {
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $owner, 'account' => $owner->getAccount()]);
        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $client = static::createClient();
        $client->loginUser($otherUser);
        $client->request('GET', \sprintf('/api/v1/export/ro-crate/%s', $project->getId()));

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function roCrateExportContainsMetadataAndInvestigationFiles(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne([
            'name' => 'Circadian Demo Investigation',
            'user' => $user,
            'account' => $user->getAccount(),
        ]);
        $experiment = ExperimentFactory::createOne([
            'name' => 'DVC Activity Study',
            'project' => $project,
            'user' => $user,
            'account' => $user->getAccount(),
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/api/v1/export/ro-crate/%s', $project->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/zip');
        $this->assertResponseHeaderSame('content-disposition', 'attachment; filename="ro-crate.zip"');
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function findGraphNode(array $metadata, string $id): array
    {
        foreach ($metadata['@graph'] ?? [] as $node) {
            if (($node['@id'] ?? null) === $id) {
                return $node;
            }
        }

        self::fail(\sprintf('Missing RO-Crate graph node "%s".', $id));
    }
}
