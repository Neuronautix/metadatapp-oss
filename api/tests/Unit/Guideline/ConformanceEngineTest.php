<?php

declare(strict_types=1);

namespace App\Tests\Unit\Guideline;

use App\Guideline\ConformanceEngine;
use App\Guideline\ConformanceEntry;
use App\Guideline\CsvColumn;
use App\Guideline\Schema\GuidelineTemplate;
use App\Guideline\Schema\RequiredColumn;
use App\Guideline\Schema\RequiredMetadata;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers the §6 algorithm: direct satisfaction, crosswalk upgrade,
 * directionality, partial (unit missing), and the "missing degrades not blocks"
 * invariant.
 */
final class ConformanceEngineTest extends TestCase
{
    private ConformanceEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new ConformanceEngine();
    }

    /**
     * @param list<RequiredMetadata> $metadata
     * @param list<RequiredColumn>   $columns
     */
    private function template(array $metadata, array $columns = []): GuidelineTemplate
    {
        return new GuidelineTemplate(
            id: 't',
            name: 'Test',
            version: '1.0',
            requiredColumns: $columns,
            requiredMetadata: $metadata,
        );
    }

    /**
     * @param list<CsvColumn>      $columns
     * @param array<string, mixed> $metadata
     */
    private function entryFor(string $fieldId, GuidelineTemplate $t, array $columns, array $metadata): ConformanceEntry
    {
        foreach ($this->engine->evaluate($t, $columns, $metadata) as $entry) {
            if ($entry->fieldId === $fieldId) {
                return $entry;
            }
        }
        self::fail(\sprintf('No entry produced for field "%s".', $fieldId));
    }

    #[Test]
    public function directMetadataSatisfaction(): void
    {
        $t = $this->template([new RequiredMetadata(id: 'species', arriveSection: 'Animals')]);

        $entry = $this->entryFor('species', $t, [], ['species' => 'Mus musculus']);

        self::assertSame(ConformanceEntry::SATISFIED, $entry->status);
        self::assertSame(['metadata' => 'species'], $entry->satisfiedBy);
    }

    #[Test]
    public function crosswalkUpgradesDeclaringField(): void
    {
        $t = $this->template([
            new RequiredMetadata(id: 'humane_endpoints', arriveSection: 'Care'),
            new RequiredMetadata(id: 'prepare_humane_endpoints', prepareSection: '3.', crosswalk: ['humane_endpoints']),
        ]);

        $entry = $this->entryFor('prepare_humane_endpoints', $t, [], ['humane_endpoints' => 'BCS<2']);

        self::assertSame(ConformanceEntry::SATISFIED, $entry->status);
        self::assertSame(['metadata' => 'humane_endpoints', 'via_crosswalk' => true], $entry->satisfiedBy);
    }

    #[Test]
    public function crosswalkIsDirectionalReverseNotInferred(): void
    {
        $t = $this->template([
            new RequiredMetadata(id: 'humane_endpoints', arriveSection: 'Care'),
            new RequiredMetadata(id: 'prepare_humane_endpoints', prepareSection: '3.', crosswalk: ['humane_endpoints']),
        ]);

        // Fill ONLY the PREPARE field; the ARRIVE field declares no crosswalk back.
        $arrive = $this->entryFor('humane_endpoints', $t, [], ['prepare_humane_endpoints' => 'planned']);

        self::assertSame(ConformanceEntry::MISSING, $arrive->status);
        self::assertNull($arrive->satisfiedBy);
    }

    #[Test]
    public function unitRequiredColumnWithoutUnitIsPartial(): void
    {
        $t = $this->template([], [
            new RequiredColumn(id: 'weight', namePatterns: ['weight'], unitRequired: true, unitColumn: 'Weight_Unit'),
        ]);

        $partial = $this->entryFor('weight', $t, [new CsvColumn('Weight')], []);
        self::assertSame(ConformanceEntry::PARTIAL, $partial->status);
        self::assertSame(['column' => 'Weight'], $partial->satisfiedBy);

        // With the companion unit column present it becomes satisfied.
        $satisfied = $this->entryFor('weight', $t, [new CsvColumn('Weight'), new CsvColumn('Weight_Unit')], []);
        self::assertSame(ConformanceEntry::SATISFIED, $satisfied->status);

        // With an explicit unit on the column it is satisfied too.
        $withUnit = $this->entryFor('weight', $t, [new CsvColumn('Weight', userUnit: 'g')], []);
        self::assertSame(ConformanceEntry::SATISFIED, $withUnit->status);
    }

    #[Test]
    public function missingDegradesButDoesNotBlock(): void
    {
        $t = $this->template([
            new RequiredMetadata(id: 'species', arriveSection: 'Animals', severity: 'high'),
            new RequiredMetadata(id: 'strain', arriveSection: 'Animals'),
        ]);

        // Empty dict: the engine still returns a full report (does not throw / block).
        $entries = $this->engine->evaluate($t, [], []);

        self::assertCount(2, $entries);
        foreach ($entries as $entry) {
            self::assertSame(ConformanceEntry::MISSING, $entry->status);
            self::assertNull($entry->satisfiedBy);
        }
    }

    #[Test]
    public function crosswalkFirstHitWinsAndPreservesOrder(): void
    {
        $t = $this->template([
            new RequiredMetadata(id: 'a'),
            new RequiredMetadata(id: 'b'),
            new RequiredMetadata(id: 'target', crosswalk: ['a', 'b']),
        ]);

        // Only 'b' filled -> target satisfied via 'b'.
        $entry = $this->entryFor('target', $t, [], ['b' => 'value']);
        self::assertSame(['metadata' => 'b', 'via_crosswalk' => true], $entry->satisfiedBy);

        // 'a' filled (first in list) wins even when both are present.
        $entry = $this->entryFor('target', $t, [], ['a' => 'x', 'b' => 'y']);
        self::assertSame(['metadata' => 'a', 'via_crosswalk' => true], $entry->satisfiedBy);
    }
}
