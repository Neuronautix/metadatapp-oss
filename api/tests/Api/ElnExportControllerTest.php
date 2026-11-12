<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Response as ApiTestResponse;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\ProjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ElnExportControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function elnExportRequiresAuthentication(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        static::createClient()->request('GET', \sprintf('/api/v1/export/eln/%s', $project->getId()));

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function elnExportForbidsOtherAccountInvestigation(): void
    {
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $owner, 'account' => $owner->getAccount()]);
        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $client = static::createClient();
        $client->loginUser($otherUser);
        $client->request('GET', \sprintf('/api/v1/export/eln/%s', $project->getId()));

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function elnExportRejectsInvalidUuid(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/api/v1/export/eln/not-a-uuid');

        $this->assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function elnExportStreamsRoCrateZip(): void
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
        $client->request('GET', \sprintf('/api/v1/export/eln/%s', $project->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/zip');

        $disposition = $client->getResponse()->getHeaders(false)['content-disposition'][0] ?? null;
        self::assertIsString($disposition);
        self::assertStringContainsString('.eln"', $disposition);

        $metadata = $this->extractRoCrateMetadata($client->getResponse());

        $graph = $metadata['@graph'] ?? [];
        self::assertNotEmpty($graph);

        $descriptor = $this->findGraphNode($graph, 'ro-crate-metadata.json');
        self::assertSame('CreativeWork', $descriptor['@type']);
        self::assertContains(
            ['@id' => 'https://w3id.org/ro/crate/1.1'],
            (array) $descriptor['conformsTo'],
        );
        self::assertSame(['@id' => '#metadatapp-fair3r'], $descriptor['sdPublisher']);

        $root = $this->findGraphNode($graph, './');
        self::assertSame('Dataset', $root['@type']);
        self::assertSame('Circadian Demo Investigation', $root['name']);

        $studyId = $experiment->getId()?->toRfc4122();
        self::assertIsString($studyId);
        $study = $this->findGraphNode($graph, \sprintf('studies/%s/', $studyId));
        self::assertSame('Dataset', $study['@type']);
        self::assertSame('DVC Activity Study', $study['name']);

        $hasPartIds = array_map(static fn (array $part): string => $part['@id'], (array) $root['hasPart']);
        self::assertContains(\sprintf('studies/%s/', $studyId), $hasPartIds);
        self::assertContains('datacite/investigation.json', $hasPartIds);
    }

    #[Test]
    public function elnStudyExportForbidsOtherAccountStudy(): void
    {
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $owner, 'account' => $owner->getAccount()]);
        $experiment = ExperimentFactory::createOne([
            'project' => $project,
            'user' => $owner,
            'account' => $owner->getAccount(),
        ]);
        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $client = static::createClient();
        $client->loginUser($otherUser);
        $client->request('GET', \sprintf('/api/v1/export/eln/study/%s', $experiment->getId()));

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function elnStudyExportStreamsRoCrateZip(): void
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
        $client->request('GET', \sprintf('/api/v1/export/eln/study/%s', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/zip');

        $disposition = $client->getResponse()->getHeaders(false)['content-disposition'][0] ?? null;
        self::assertIsString($disposition);
        self::assertStringContainsString('metadatapp-study-', $disposition);
        self::assertStringContainsString('.eln"', $disposition);

        $metadata = $this->extractRoCrateMetadata($client->getResponse());
        $graph = $metadata['@graph'] ?? [];

        $descriptor = $this->findGraphNode($graph, 'ro-crate-metadata.json');
        self::assertContains(
            ['@id' => 'https://w3id.org/ro/crate/1.1'],
            (array) $descriptor['conformsTo'],
        );

        $root = $this->findGraphNode($graph, './');
        self::assertSame('Dataset', $root['@type']);
        self::assertSame('DVC Activity Study', $root['name']);

        $hasPartIds = array_map(static fn (array $part): string => $part['@id'], (array) $root['hasPart']);
        self::assertContains('datacite/study.json', $hasPartIds);

        $this->findGraphNode($graph, 'datacite/study.json');
    }

    /**
     * @return array<string, mixed>
     */
    private function extractRoCrateMetadata(ResponseInterface $response): array
    {
        \assert($response instanceof ApiTestResponse);
        $binary = (string) $response->getBrowserKitResponse()->getContent();

        $zipPath = tempnam(sys_get_temp_dir(), 'eln') . '.zip';
        file_put_contents($zipPath, $binary);

        $zip = new \ZipArchive();
        self::assertTrue(true === $zip->open($zipPath), 'ELN archive could not be opened.');

        $metadataJson = null;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if (\is_string($name) && str_ends_with($name, 'ro-crate-metadata.json')) {
                $metadataJson = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();
        @unlink($zipPath);

        self::assertIsString($metadataJson, 'ro-crate-metadata.json missing from ELN archive.');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($metadataJson, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param list<array<string, mixed>> $graph
     *
     * @return array<string, mixed>
     */
    private function findGraphNode(array $graph, string $id): array
    {
        foreach ($graph as $node) {
            if (($node['@id'] ?? null) === $id) {
                return $node;
            }
        }

        self::fail(\sprintf('Missing RO-Crate graph node "%s".', $id));
    }
}
