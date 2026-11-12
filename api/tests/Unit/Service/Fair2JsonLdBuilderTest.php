<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ExperimentType;
use App\Service\Fair2JsonLdBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class Fair2JsonLdBuilderTest extends TestCase
{
    private Fair2JsonLdBuilder $builder;
    private const string BASE_URL = 'https://app.example.com';

    protected function setUp(): void
    {
        $this->builder = new Fair2JsonLdBuilder();
    }

    private function makeUser(string $firstName = 'Jane', string $lastName = 'Doe', string $email = 'jane@example.com', ?string $orcid = null): User
    {
        $user = new User();
        $user->firstName = $firstName;
        $user->lastName = $lastName;
        $user->email = $email;
        if (null !== $orcid) {
            $user->setOrcid($orcid);
        }

        return $user;
    }

    private function makeProject(User $user, string $name = 'Test Investigation'): Project
    {
        $project = new Project();
        $project->setName($name);
        $project->setGoal('Study neuroplasticity');
        $project->setDescription('A comprehensive study.');
        $project->setStartAt(new \DateTime('2024-01-01'));
        $project->setUser($user);

        return $project;
    }

    private function makeExperiment(User $user, string $name = 'Test Study'): Experiment
    {
        $experiment = new Experiment();
        $experiment->setName($name);
        $experiment->setGoal('Map cortical responses');
        $experiment->setStartAt(new \DateTime('2024-02-01'));
        $experiment->setType(ExperimentType::TypeA);
        $experiment->setUser($user);
        $experiment->setProtocol('Standard protocol v2');

        return $experiment;
    }

    #[Test]
    public function buildForProjectReturnsValidCroissantStructure(): void
    {
        $user = $this->makeUser();
        $project = $this->makeProject($user);
        $project->setId(Uuid::v4());

        $document = $this->builder->buildForProject($project, self::BASE_URL);

        $this->assertArrayHasKey('@graph', $document);
        $root = $document['@graph'][0];
        $this->assertContains('sc:Dataset', $root['@type']);
        $this->assertSame('http://mlcommons.org/croissant/1.0', $root['cr:conformsTo']);
        $this->assertSame('Test Investigation', $root['name']);
        $this->assertSame('https://creativecommons.org/licenses/by/4.0/', $root['license']);
    }

    #[Test]
    public function buildForProjectContainsFair2Context(): void
    {
        $user = $this->makeUser();
        $project = $this->makeProject($user);

        $document = $this->builder->buildForProject($project, self::BASE_URL);

        $this->assertArrayHasKey('@context', $document);
        $context = $document['@context'];
        $this->assertSame('https://fair2.science/ns/', $context['fa2']);
        $this->assertSame('http://mlcommons.org/croissant/', $context['cr']);
        $this->assertSame('http://qudt.org/schema/qudt/', $context['qudt']);
        $this->assertSame('https://schema.org/', $context['sc']);
    }

    #[Test]
    public function buildForProjectContainsCreatorWithUserData(): void
    {
        $user = $this->makeUser('Alice', 'Smith', 'alice@lab.org');
        $project = $this->makeProject($user);
        $project->setId(Uuid::v4());

        $document = $this->builder->buildForProject($project, self::BASE_URL);

        $root = $document['@graph'][0];
        $this->assertArrayHasKey('creator', $root);
        $creator = $root['creator'];
        $this->assertSame('sc:Person', $creator['@type']);
        $this->assertSame('Alice Smith', $creator['name']);
        // Email is PII and omitted from public endpoints until an isPublic/consent flag is introduced.
        $this->assertArrayNotHasKey('email', $creator);
    }

    #[Test]
    public function buildForProjectCreatorIncludesOrcidWhenSet(): void
    {
        $user = $this->makeUser('Bob', 'Jones', 'bob@lab.org', '0000-0001-2345-6789');
        $project = $this->makeProject($user);
        $project->setId(Uuid::v4());

        $document = $this->builder->buildForProject($project, self::BASE_URL);

        $root = $document['@graph'][0];
        $creator = $root['creator'];
        $this->assertSame('https://orcid.org/0000-0001-2345-6789', $creator['sameAs']);
    }

    #[Test]
    public function buildForProjectIncludesStudiesInHasPart(): void
    {
        $user = $this->makeUser();
        $project = $this->makeProject($user);
        $project->setId(Uuid::v4());

        $experiment = $this->makeExperiment($user, 'Study A');
        $experiment->setId(Uuid::v4());
        $project->addExperiment($experiment);

        $document = $this->builder->buildForProject($project, self::BASE_URL);

        $root = $document['@graph'][0];
        $this->assertArrayHasKey('hasPart', $root);
        $this->assertCount(1, $root['hasPart']);
        // hasPart now contains @id references, not full objects
        $this->assertArrayHasKey('@id', $root['hasPart'][0]);
        // The full study node is in @graph[1]
        $studyNode = $document['@graph'][1];
        $this->assertContains('sc:Dataset', $studyNode['@type']);
        $this->assertSame('Study A', $studyNode['name']);
    }

    #[Test]
    public function buildForProjectSetsCorrectAtId(): void
    {
        $user = $this->makeUser();
        $project = $this->makeProject($user);
        $uuid = Uuid::v4();
        $project->setId($uuid);

        $document = $this->builder->buildForProject($project, self::BASE_URL);

        $root = $document['@graph'][0];
        $expectedId = \sprintf('%s/investigations/%s/fair2.json', self::BASE_URL, $uuid->toRfc4122());
        $this->assertSame($expectedId, $root['@id']);
    }

    #[Test]
    public function buildForExperimentReturnsValidCroissantStructure(): void
    {
        $user = $this->makeUser();
        $experiment = $this->makeExperiment($user);
        $experiment->setId(Uuid::v4());

        $document = $this->builder->buildForExperiment($experiment, self::BASE_URL);

        $this->assertArrayHasKey('@graph', $document);
        $root = $document['@graph'][0];
        $this->assertContains('sc:Dataset', $root['@type']);
        $this->assertSame('http://mlcommons.org/croissant/1.0', $root['cr:conformsTo']);
        $this->assertSame('Test Study', $root['name']);
        $this->assertSame('https://creativecommons.org/licenses/by/4.0/', $root['license']);
        $this->assertSame('Standard protocol v2', $root['fa2:method']);
    }

    #[Test]
    public function buildForExperimentSetsDateCreatedFromStartAt(): void
    {
        $user = $this->makeUser();
        $experiment = $this->makeExperiment($user);
        $experiment->setId(Uuid::v4());

        $document = $this->builder->buildForExperiment($experiment, self::BASE_URL);

        $root = $document['@graph'][0];
        $this->assertSame('2024-02-01', $root['dateCreated']);
    }

    #[Test]
    public function buildForExperimentFiltersOutNullFair2Fields(): void
    {
        $user = $this->makeUser();
        $experiment = $this->makeExperiment($user);
        $experiment->setId(Uuid::v4());
        $experiment->setProtocol(null);

        $document = $this->builder->buildForExperiment($experiment, self::BASE_URL);

        $root = $document['@graph'][0];
        $this->assertArrayNotHasKey('fa2:method', $root);
    }

    #[Test]
    public function buildForExperimentIncludesIsPartOfWhenProjectSet(): void
    {
        $user = $this->makeUser();
        $project = $this->makeProject($user, 'Parent Investigation');
        $projectUuid = Uuid::v4();
        $project->setId($projectUuid);

        $experiment = $this->makeExperiment($user);
        $experiment->setId(Uuid::v4());
        $experiment->setProject($project);

        $document = $this->builder->buildForExperiment($experiment, self::BASE_URL);

        $root = $document['@graph'][0];
        $this->assertArrayHasKey('isPartOf', $root);
        $this->assertContains('sc:Dataset', $root['isPartOf']['@type']);
        $this->assertSame('Parent Investigation', $root['isPartOf']['name']);
    }
}
