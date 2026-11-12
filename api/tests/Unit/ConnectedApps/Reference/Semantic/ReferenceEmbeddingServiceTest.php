<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Semantic;

use App\ConnectedApps\Reference\Semantic\HashingEmbeddingProvider;
use App\ConnectedApps\Reference\Semantic\ReferenceEmbeddingService;
use App\Entity\Account;
use App\Entity\ImportedReference;
use App\Repository\ImportedReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the pure-PHP (pgvector-free) cosine ranking. No database, no extension:
 * the repository is stubbed with in-memory ImportedReference objects and the
 * deterministic HashingEmbeddingProvider supplies the vectors.
 */
final class ReferenceEmbeddingServiceTest extends TestCase
{
    #[Test]
    public function findSimilarToTextRanksTheClosestLibraryItemFirst(): void
    {
        $account = new Account();
        $library = [
            $this->reference($account, 'jax:1', 'Tail suspension immobility time', 'Depression-related behaviour'),
            $this->reference($account, 'jax:2', 'Blood glucose level', 'Plasma glucose concentration'),
            $this->reference($account, 'jax:3', 'Grip strength forelimb', 'Neuromuscular function'),
        ];

        $service = $this->service($account, $library);

        $matches = $service->findSimilarToText($account, 'blood glucose measurement', 3);

        self::assertNotEmpty($matches);
        self::assertSame('jax:2', $matches[0]['reference']->getReferenceId(), 'The glucose item ranks first.');
        // Scores are sorted descending.
        for ($i = 1; $i < \count($matches); ++$i) {
            self::assertLessThanOrEqual($matches[$i - 1]['score'], $matches[$i]['score']);
        }
    }

    #[Test]
    public function findSimilarToReferenceExcludesTheReferenceItself(): void
    {
        $account = new Account();
        $target = $this->reference($account, 'jax:2', 'Blood glucose level', 'Plasma glucose concentration');
        $library = [
            $target,
            $this->reference($account, 'jax:9', 'Blood glucose concentration', 'Glucose in plasma'),
            $this->reference($account, 'jax:3', 'Grip strength forelimb', 'Neuromuscular function'),
        ];

        $service = $this->service($account, $library);

        $matches = $service->findSimilarToReference($target, 5);

        $ids = array_map(static fn (array $m): string => $m['reference']->getReferenceId(), $matches);
        self::assertNotContains('jax:2', $ids, 'The reference must not be returned as its own neighbour.');
        self::assertSame('jax:9', $ids[0] ?? null, 'The lexically-equivalent glucose item ranks first.');
    }

    #[Test]
    public function findSimilarToEmptyTextReturnsNothing(): void
    {
        $account = new Account();
        $service = $this->service($account, [$this->reference($account, 'jax:1', 'Body weight', null)]);

        self::assertSame([], $service->findSimilarToText($account, '   ', 5));
    }

    #[Test]
    public function embedReferencePopulatesAStoredVector(): void
    {
        $account = new Account();
        $reference = $this->reference($account, 'jax:1', 'Open field test', 'Locomotor activity');

        $vector = $this->service($account, [$reference])->embedReference($reference);

        self::assertCount(256, $vector);
        self::assertNotNull($reference->getEmbedding());
        self::assertSame($vector, json_decode((string) $reference->getEmbedding(), true));
    }

    /**
     * @param list<ImportedReference> $library
     */
    private function service(Account $account, array $library): ReferenceEmbeddingService
    {
        $repository = $this->createStub(ImportedReferenceRepository::class);
        $repository->method('findAllForAccount')->willReturnCallback(
            static fn (Account $a): array => $a === $account ? $library : [],
        );

        return new ReferenceEmbeddingService(
            new HashingEmbeddingProvider(),
            $repository,
            $this->createStub(EntityManagerInterface::class),
        );
    }

    private function reference(Account $account, string $referenceId, string $title, ?string $description): ImportedReference
    {
        return (new ImportedReference())
            ->setAccount($account)
            ->setReferenceId($referenceId)
            ->setSource('jax_phenome')
            ->setSourceName('JAX Phenome (MPD)')
            ->setType('measure')
            ->setTitle($title)
            ->setDescription($description);
    }
}
