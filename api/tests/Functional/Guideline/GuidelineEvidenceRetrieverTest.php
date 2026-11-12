<?php

declare(strict_types=1);

namespace App\Tests\Functional\Guideline;

use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\BehaviorFactory;
use App\DataFixtures\Factory\CageFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\StrainFactory;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Account;
use App\Entity\Experiment;
use App\Entity\GuidelineFieldValue;
use App\Entity\User;
use App\Enum\Sex;
use App\Enum\Species;
use App\Guideline\Evidence\EvidenceItem;
use App\Guideline\Evidence\GuidelineEvidenceRetriever;
use App\Guideline\Schema\RequiredMetadata;
use App\Repository\GuidelineFieldValueRepository;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Deterministic computed-facts + cross-investigation history reuse, plus the
 * non-negotiable tenant-isolation invariant (account B values never reach
 * account A).
 */
final class GuidelineEvidenceRetrieverTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private function retriever(): GuidelineEvidenceRetriever
    {
        return self::getContainer()->get(GuidelineEvidenceRetriever::class);
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    #[Test]
    public function itComputesDeterministicSubjectFacts(): void
    {
        self::bootKernel();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();
        $strain = StrainFactory::createOne(['name' => 'C57BL/6J']);

        $experiment = ExperimentFactory::createOne(['account' => $account, 'user' => $user]);
        $procedure = BehaviorFactory::createOne([
            'account' => $account,
            'user' => $user,
            'experiment' => $experiment,
        ]);
        $cage = CageFactory::createOne(['account' => $account, 'homeCageMonitoring' => null]);

        // 3 subjects: 2 male, 1 female, all in one cage, same strain.
        SubjectFactory::createMany(2, [
            'account' => $account,
            'strain' => $strain,
            'cage' => $cage,
            'species' => Species::Mouse,
            'sex' => Sex::Male,
            'weight' => 25.0,
            'procedures' => [$procedure],
        ]);
        SubjectFactory::createOne([
            'account' => $account,
            'strain' => $strain,
            'cage' => $cage,
            'species' => Species::Mouse,
            'sex' => Sex::Female,
            'weight' => 22.0,
            'procedures' => [$procedure],
        ]);

        $entity = $this->findExperiment($experiment->getId());
        $facts = $this->retriever()->computeFacts([$entity]);

        self::assertSame(3, $facts->totalSubjects);
        self::assertSame(2, $facts->sexCounts['male'] ?? 0);
        self::assertSame(1, $facts->sexCounts['female'] ?? 0);
        self::assertSame(['mouse'], $facts->species);
        self::assertSame(['C57BL/6J'], $facts->strains);
        self::assertTrue($facts->hasWeight);
        // 3 subjects in 1 cage => density 3.
        self::assertSame(3.0, $facts->housingDensity);

        // The bundle exposes those as computed evidence items.
        $bundle = $this->retriever()->retrieveForStudy($entity);
        $values = array_map(static fn (EvidenceItem $i): string => $i->value, $bundle->computedFacts());
        self::assertContains('3', $values, 'total subject count is a computed fact');
        self::assertContains('mouse', $values);
    }

    #[Test]
    public function itReusesTheLatestCrossInvestigationHistoryValueWithinTheAccount(): void
    {
        self::bootKernel();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();

        $otherStudy = ExperimentFactory::createOne(['account' => $account, 'user' => $user, 'name' => 'Prior Study Foo']);
        $currentStudy = ExperimentFactory::createOne(['account' => $account, 'user' => $user, 'name' => 'Current Study']);

        $this->persistFieldValue(
            GuidelineFieldValue::RESOURCE_STUDY,
            $otherStudy->getId(),
            'ethics_statement',
            'Approved under licence PPL-1234.',
            $account,
            $user,
        );

        $current = $this->findExperiment($currentStudy->getId());
        $bundle = $this->retriever()->retrieveForStudy($current);

        $history = array_values(array_filter(
            $bundle->all(),
            static fn (EvidenceItem $i): bool => EvidenceItem::SOURCE_HISTORY === $i->source && 'ethics_statement' === $i->fieldId,
        ));

        self::assertCount(1, $history);
        self::assertSame('Approved under licence PPL-1234.', $history[0]->value);
        self::assertStringContainsString('Prior Study Foo', $history[0]->provenance);
    }

    #[Test]
    public function itSkipsAnEmptyLatestFillAndReusesTheLatestNonEmptyHistoryValue(): void
    {
        self::bootKernel();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();

        $olderStudy = ExperimentFactory::createOne(['account' => $account, 'user' => $user, 'name' => 'Older Study With Value']);
        $newerStudy = ExperimentFactory::createOne(['account' => $account, 'user' => $user, 'name' => 'Newer Study Cleared']);
        $currentStudy = ExperimentFactory::createOne(['account' => $account, 'user' => $user, 'name' => 'Current Study']);

        // Older resource holds a real value.
        $this->persistFieldValue(
            GuidelineFieldValue::RESOURCE_STUDY,
            $olderStudy->getId(),
            'ethics_statement',
            'Approved under licence PPL-9999.',
            $account,
            $user,
            new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
        // Newer resource has the SAME field cleared to empty (more recent updatedAt).
        $this->persistFieldValue(
            GuidelineFieldValue::RESOURCE_STUDY,
            $newerStudy->getId(),
            'ethics_statement',
            '',
            $account,
            $user,
            new \DateTimeImmutable('2026-06-01T00:00:00Z'),
        );

        $current = $this->findExperiment($currentStudy->getId());
        $bundle = $this->retriever()->retrieveForStudy($current);

        $history = array_values(array_filter(
            $bundle->all(),
            static fn (EvidenceItem $i): bool => EvidenceItem::SOURCE_HISTORY === $i->source && 'ethics_statement' === $i->fieldId,
        ));

        // The empty newer fill must NOT mask the older real value.
        self::assertCount(1, $history, 'the empty latest fill must not hide the latest non-empty value');
        self::assertSame('Approved under licence PPL-9999.', $history[0]->value);

        // The repository helper itself must return the latest NON-EMPTY value.
        $repo = self::getContainer()->get(GuidelineFieldValueRepository::class);
        $accountManaged = $this->findAccount($account->getId());
        $latest = $repo->findLatestForFieldAcrossAccount('ethics_statement', $accountManaged);
        self::assertNotNull($latest);
        self::assertSame('Approved under licence PPL-9999.', $latest->getValue());
    }

    #[Test]
    public function itNeverReturnsAHistoryValueFromAnotherAccount(): void
    {
        self::bootKernel();

        // Account A: the current resource.
        $userA = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $accountA = $userA->getAccount();
        $studyA = ExperimentFactory::createOne(['account' => $accountA, 'user' => $userA]);

        // Account B: a DIFFERENT tenant with a stored fill for the same field.
        $userB = UserFactory::createOne(['roles' => [Roles::ROLE_USER], 'account' => AccountFactory::createOne()]);
        $accountB = $userB->getAccount();
        $studyB = ExperimentFactory::createOne(['account' => $accountB, 'user' => $userB]);
        $this->persistFieldValue(
            GuidelineFieldValue::RESOURCE_STUDY,
            $studyB->getId(),
            'ethics_statement',
            'SECRET cross-account ethics value',
            $accountB,
            $userB,
        );

        $current = $this->findExperiment($studyA->getId());
        $bundle = $this->retriever()->retrieveForStudy($current);

        foreach ($bundle->all() as $item) {
            self::assertStringNotContainsString('SECRET cross-account', $item->value, 'tenant isolation: account B value must never reach account A');
        }

        // And the repository helper itself isolates accounts.
        $repo = self::getContainer()->get(GuidelineFieldValueRepository::class);
        $accountAManaged = $this->findAccount($accountA->getId());
        $accountBManaged = $this->findAccount($accountB->getId());
        self::assertNull($repo->findLatestForFieldAcrossAccount('ethics_statement', $accountAManaged));
        self::assertNotNull($repo->findLatestForFieldAcrossAccount('ethics_statement', $accountBManaged));
    }

    #[Test]
    public function theBundleAttachesReferenceCitationsPerStandardButNotAsSuggestions(): void
    {
        self::bootKernel();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();
        $experiment = ExperimentFactory::createOne(['account' => $account, 'user' => $user]);

        $entity = $this->findExperiment($experiment->getId());
        $bundle = $this->retriever()->retrieveForStudy($entity);

        // ARRIVE field: canonical text + arriveguidelines.org url.
        $arriveField = new RequiredMetadata(id: 'species', arriveSection: 'Experimental animals / Species', severity: 'high');
        $arriveRefs = array_values(array_filter(
            $bundle->forField($arriveField),
            static fn (EvidenceItem $i): bool => EvidenceItem::SOURCE_REFERENCE === $i->source,
        ));
        self::assertCount(1, $arriveRefs);
        self::assertNotNull($arriveRefs[0]->url);
        self::assertStringContainsString('arriveguidelines.org', (string) $arriveRefs[0]->url);
        self::assertStringContainsString('species-appropriate details', $arriveRefs[0]->value);
        // The full evidence shape carries the new url key.
        self::assertSame($arriveRefs[0]->url, $arriveRefs[0]->toArray()['url']);

        // PREPARE field maps to the nc3rs PREPARE url.
        $prepareField = new RequiredMetadata(id: 'prepare_humane_endpoints', prepareSection: '3. Ethical issues, humane endpoints');
        $prepareRefs = array_values(array_filter(
            $bundle->forField($prepareField),
            static fn (EvidenceItem $i): bool => EvidenceItem::SOURCE_REFERENCE === $i->source,
        ));
        self::assertCount(1, $prepareRefs);
        self::assertStringContainsString('nc3rs.org.uk', (string) $prepareRefs[0]->url);

        // EQIPD field maps to eqipd.eu.
        $eqipdField = new RequiredMetadata(id: 'eqipd_data_documentation', eqipdSection: 'Data integrity');
        $eqipdRefs = array_values(array_filter(
            $bundle->forField($eqipdField),
            static fn (EvidenceItem $i): bool => EvidenceItem::SOURCE_REFERENCE === $i->source,
        ));
        self::assertCount(1, $eqipdRefs);
        self::assertStringContainsString('eqipd.eu', (string) $eqipdRefs[0]->url);

        // Reference items are NEVER offered as deterministic suggestions.
        foreach ([$arriveField, $prepareField, $eqipdField] as $field) {
            foreach ($bundle->suggestionsForField($field) as $s) {
                self::assertNotSame(EvidenceItem::SOURCE_REFERENCE, $s->source);
            }
        }
    }

    #[Test]
    public function aFieldWithNoStrongMatchDegradesGracefully(): void
    {
        self::bootKernel();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();
        $experiment = ExperimentFactory::createOne(['account' => $account, 'user' => $user]);

        $entity = $this->findExperiment($experiment->getId());
        $bundle = $this->retriever()->retrieveForStudy($entity);

        // A field whose tokens overlap little still yields at most a low-confidence
        // standard-url citation — never an exception.
        $field = new RequiredMetadata(id: 'eqipd_process_owner', eqipdSection: 'Research team');
        $refs = array_values(array_filter(
            $bundle->forField($field),
            static fn (EvidenceItem $i): bool => EvidenceItem::SOURCE_REFERENCE === $i->source,
        ));

        self::assertCount(1, $refs);
        self::assertStringContainsString('eqipd.eu', (string) $refs[0]->url);
        self::assertNotNull($refs[0]->confidence);
    }

    private function findExperiment(?Uuid $id): Experiment
    {
        self::assertNotNull($id);
        /** @var Experiment|null $experiment */
        $experiment = $this->em()->getRepository(Experiment::class)->find($id);
        self::assertNotNull($experiment);

        return $experiment;
    }

    private function findAccount(?Uuid $id): Account
    {
        self::assertNotNull($id);
        /** @var Account|null $account */
        $account = $this->em()->getRepository(Account::class)->find($id);
        self::assertNotNull($account);

        return $account;
    }

    private function persistFieldValue(
        string $resourceType,
        ?Uuid $resourceId,
        string $fieldId,
        string $value,
        Account $account,
        User $user,
        ?\DateTimeImmutable $updatedAt = null,
    ): void {
        self::assertNotNull($resourceId);
        $em = $this->em();
        $entity = new GuidelineFieldValue();
        $entity->setResourceType($resourceType)
            ->setResourceId($resourceId)
            ->setTemplateId('test-template')
            ->setFieldId($fieldId)
            ->setValue($value)
            ->setSource(GuidelineFieldValue::SOURCE_MANUAL)
            ->setAccount($account)
            ->setUser($user)
            ->touch()
        ;
        $em->persist($entity);
        $em->flush();

        if (null !== $updatedAt) {
            // Force a deterministic updatedAt so "latest" ordering is testable.
            $em->createQueryBuilder()
                ->update(GuidelineFieldValue::class, 'v')
                ->set('v.updatedAt', ':updatedAt')
                ->where('v.id = :id')
                ->setParameter('updatedAt', $updatedAt)
                ->setParameter('id', $entity->getId(), 'uuid')
                ->getQuery()
                ->execute()
            ;
            // Reload the managed entity so its updatedAt reflects the forced value.
            $em->refresh($entity);
        }
    }
}
