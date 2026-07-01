<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Eval;

use App\AI\Eval\StandardizationEvaluator;
use App\ConnectedApps\Reference\Standardization\CanonicalTerm;
use App\ConnectedApps\Reference\Standardization\ReferenceCluster;
use App\ConnectedApps\Reference\Standardization\StandardizationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pure, deterministic checks of the clustering precision/recall + canonical-term
 * accuracy math. No provider, no network — the evaluator only ever sees a golden
 * `expected` block and an already-assembled {@see StandardizationResult}.
 */
final class StandardizationEvaluatorTest extends TestCase
{
    /**
     * @var array<string, list<string>> three concepts, 7 cross-source ids (matches
     *                                   the seed fixture's first three concepts)
     */
    private const EXPECTED = [
        'http://purl.obolibrary.org/obo/VT_0000188' => ['jax_phenome:measure:1', 'impc:measure:2', 'nih_cde:cde_element:3'],
        'http://purl.obolibrary.org/obo/VT_0001259' => ['jax_phenome:measure:4', 'impc:measure:5'],
        'http://purl.obolibrary.org/obo/MP_0001515' => ['impc:measure:6', 'nih_cde:cde_element:7'],
    ];

    #[Test]
    public function aPerfectResultScoresOneAcrossEveryMetric(): void
    {
        $result = new StandardizationResult(
            clusters: [
                $this->cluster('http://purl.obolibrary.org/obo/VT_0000188', ['jax_phenome:measure:1', 'impc:measure:2', 'nih_cde:cde_element:3']),
                $this->cluster('http://purl.obolibrary.org/obo/VT_0001259', ['jax_phenome:measure:4', 'impc:measure:5']),
                $this->cluster('http://purl.obolibrary.org/obo/MP_0001515', ['impc:measure:6', 'nih_cde:cde_element:7']),
            ],
            aiAvailable: true,
        );

        $scores = (new StandardizationEvaluator())->evaluate(['clusters' => self::EXPECTED], $result);

        self::assertSame(1.0, $scores->precision);
        self::assertSame(1.0, $scores->recall);
        self::assertSame(1.0, $scores->termAccuracy);
        self::assertSame(1.0, $scores->f1());
        self::assertSame(3, $scores->expectedClusterCount);
        self::assertSame(3, $scores->producedClusterCount);
        foreach ($scores->perConcept as $concept) {
            self::assertTrue($concept->termMatched);
        }
    }

    #[Test]
    public function splittingAClusterCostsRecallButNotPrecision(): void
    {
        // The 3-member glucose cluster is split into a 2-member cluster + a singleton.
        // Gold should-link pairs = 3 (glucose: 3 pairs) + 1 (weight) + 1 (grip) = 5.
        // The split keeps only 1 of glucose's 3 pairs (the 2 that stayed together),
        // so TP = 1 (glucose) + 1 (weight) + 1 (grip) = 3, FN = 2 (the two pairs the
        // singleton no longer shares). No wrong pairs are produced -> precision 1.0.
        $result = new StandardizationResult(
            clusters: [
                $this->cluster('http://purl.obolibrary.org/obo/VT_0000188', ['jax_phenome:measure:1', 'impc:measure:2']),
                $this->cluster('http://purl.obolibrary.org/obo/VT_0000188', ['nih_cde:cde_element:3']),
                $this->cluster('http://purl.obolibrary.org/obo/VT_0001259', ['jax_phenome:measure:4', 'impc:measure:5']),
                $this->cluster('http://purl.obolibrary.org/obo/MP_0001515', ['impc:measure:6', 'nih_cde:cde_element:7']),
            ],
            aiAvailable: true,
        );

        $scores = (new StandardizationEvaluator())->evaluate(['clusters' => self::EXPECTED], $result);

        self::assertSame(1.0, $scores->precision);
        self::assertEqualsWithDelta(3 / 5, $scores->recall, 1e-9);
    }

    #[Test]
    public function overMergingTwoConceptsCostsPrecisionAndTermAccuracy(): void
    {
        // Body weight (2 ids) and grip strength (2 ids) are wrongly merged into one
        // cluster, and glucose is split into singletons (no gold pairs survive there).
        // Produced pairs in the merged cluster: C(4,2)=6, of which only 2 are gold
        // (the weight pair + the grip pair) -> TP=2, FP=4 -> precision 2/6 = 1/3.
        // Gold pairs total 5; glucose's 3 are all missed, so recall = 2/5.
        $result = new StandardizationResult(
            clusters: [
                $this->cluster('http://purl.obolibrary.org/obo/VT_0000188', ['jax_phenome:measure:1']),
                $this->cluster('http://purl.obolibrary.org/obo/VT_0000188', ['impc:measure:2']),
                $this->cluster('http://purl.obolibrary.org/obo/VT_0000188', ['nih_cde:cde_element:3']),
                $this->cluster('http://purl.obolibrary.org/obo/VT_0001259', ['jax_phenome:measure:4', 'impc:measure:5', 'impc:measure:6', 'nih_cde:cde_element:7']),
            ],
            aiAvailable: true,
        );

        $scores = (new StandardizationEvaluator())->evaluate(['clusters' => self::EXPECTED], $result);

        self::assertEqualsWithDelta(2 / 6, $scores->precision, 1e-9);
        self::assertEqualsWithDelta(2 / 5, $scores->recall, 1e-9);
        // Grip strength's best-overlap cluster carries the body-weight IRI, not MP_…,
        // and glucose's singletons all carry the right IRI but with overlap 1 each,
        // so glucose still counts as correct (term carried on its best cluster).
        // Term accuracy: glucose OK, body-weight OK (the merged cluster carries VT_0001259
        // and overlaps weight), grip WRONG -> 2/3.
        self::assertEqualsWithDelta(2 / 3, $scores->termAccuracy, 1e-9);
    }

    #[Test]
    public function aWrongCanonicalIriIsAMissedTermEvenWhenMembersAreRight(): void
    {
        // Perfect clustering, but the glucose cluster carries the wrong IRI.
        $result = new StandardizationResult(
            clusters: [
                $this->cluster('http://purl.obolibrary.org/obo/WRONG_0000001', ['jax_phenome:measure:1', 'impc:measure:2', 'nih_cde:cde_element:3']),
                $this->cluster('http://purl.obolibrary.org/obo/VT_0001259', ['jax_phenome:measure:4', 'impc:measure:5']),
                $this->cluster('http://purl.obolibrary.org/obo/MP_0001515', ['impc:measure:6', 'nih_cde:cde_element:7']),
            ],
            aiAvailable: true,
        );

        $scores = (new StandardizationEvaluator())->evaluate(['clusters' => self::EXPECTED], $result);

        self::assertSame(1.0, $scores->precision);
        self::assertSame(1.0, $scores->recall);
        self::assertEqualsWithDelta(2 / 3, $scores->termAccuracy, 1e-9);
    }

    #[Test]
    public function invalidIdsAndMissingClustersDegradeGracefully(): void
    {
        // Only one concept is produced (glucose, correct), the others are absent;
        // an invented id is ignored for pair scoring.
        $result = new StandardizationResult(
            clusters: [
                $this->cluster('http://purl.obolibrary.org/obo/VT_0000188', ['jax_phenome:measure:1', 'impc:measure:2', 'nih_cde:cde_element:3', 'hallucinated:999']),
            ],
            aiAvailable: true,
        );

        $scores = (new StandardizationEvaluator())->evaluate(['clusters' => self::EXPECTED], $result);

        // Glucose's 3 pairs are all correct, no false pairs (invented id filtered out)
        // -> precision 1.0; weight + grip pairs missing -> recall 3/5.
        self::assertSame(1.0, $scores->precision);
        self::assertEqualsWithDelta(3 / 5, $scores->recall, 1e-9);
        // Only glucose's term is matched.
        self::assertEqualsWithDelta(1 / 3, $scores->termAccuracy, 1e-9);
        self::assertSame(3, $scores->expectedClusterCount);
        self::assertSame(1, $scores->producedClusterCount);
    }

    /**
     * @param list<string> $members
     */
    private function cluster(string $iri, array $members): ReferenceCluster
    {
        return new ReferenceCluster(
            clusterId: 'c-' . substr(md5($iri . implode(',', $members)), 0, 8),
            canonicalTerm: new CanonicalTerm($iri, 'label', 'vt', 0.9),
            memberRefIds: $members,
            evidence: [],
            proposedRecord: [],
            confidence: 0.9,
        );
    }
}
