<?php

declare(strict_types=1);

namespace App\Tests\Unit\Guideline;

use App\Guideline\ConformanceEntry;
use App\Guideline\Report\CompletionReport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers §8.2: totals split, by_severity, and the multi-section membership
 * invariant (a field appears under each of its arrive/prepare/eqipd sections).
 */
final class CompletionReportTest extends TestCase
{
    #[Test]
    public function totalsSplitDirectFromCrosswalk(): void
    {
        $entries = [
            new ConformanceEntry('S', 'A', 'A', null, null, 'f1', ConformanceEntry::SATISFIED, ['metadata' => 'f1'], 'high', false),
            new ConformanceEntry('S', 'A', 'A', null, null, 'f2', ConformanceEntry::SATISFIED, ['metadata' => 'x', 'via_crosswalk' => true], 'low', false),
            new ConformanceEntry('S', 'A', 'A', null, null, 'f3', ConformanceEntry::PARTIAL, ['column' => 'c'], 'low', true),
            new ConformanceEntry('S', 'A', 'A', null, null, 'f4', ConformanceEntry::MISSING, null, 'medium', false),
        ];

        $report = new CompletionReport('t', 'S', $entries);

        self::assertSame(
            ['satisfied_direct' => 1, 'satisfied_via_crosswalk' => 1, 'partial' => 1, 'missing' => 1],
            $report->totals(),
        );
    }

    #[Test]
    public function fieldAppearsUnderEachOfItsSections(): void
    {
        // A single field declaring both an ARRIVE and a PREPARE section.
        $entry = new ConformanceEntry(
            'PREPARE',
            'Animal care and monitoring / Humane endpoints',
            'Animal care and monitoring / Humane endpoints',
            '3. Ethical issues',
            null,
            'prepare_humane_endpoints',
            ConformanceEntry::SATISFIED,
            ['metadata' => 'prepare_humane_endpoints'],
            'high',
            false,
        );

        $report = new CompletionReport('t', 'PREPARE', [$entry]);
        $bySection = $report->bySection();

        self::assertArrayHasKey('Animal care and monitoring / Humane endpoints', $bySection);
        self::assertArrayHasKey('3. Ethical issues', $bySection);
        self::assertContains('prepare_humane_endpoints', $bySection['Animal care and monitoring / Humane endpoints']['fields']);
        self::assertContains('prepare_humane_endpoints', $bySection['3. Ethical issues']['fields']);
    }

    #[Test]
    public function bySeverityCounts(): void
    {
        $entries = [
            new ConformanceEntry('S', 'A', 'A', null, null, 'f1', ConformanceEntry::SATISFIED, ['metadata' => 'f1'], 'high', false),
            new ConformanceEntry('S', 'A', 'A', null, null, 'f2', ConformanceEntry::MISSING, null, 'high', false),
        ];

        $report = new CompletionReport('t', 'S', $entries);

        self::assertSame(['satisfied' => 1, 'partial' => 0, 'missing' => 1], $report->bySeverity()['high']);
    }
}
