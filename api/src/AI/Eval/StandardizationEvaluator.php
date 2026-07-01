<?php

declare(strict_types=1);

namespace App\AI\Eval;

use App\ConnectedApps\Reference\Standardization\StandardizationResult;

/**
 * Scores a {@see StandardizationResult} against a hand-labelled golden set for the
 * AI "Standardize References" feature, so we can track clustering quality and
 * canonical-term accuracy across model/prompt changes.
 *
 * The golden set is the JSON shape seeded in
 * `tests/Unit/ConnectedApps/Reference/Standardization/fixtures/standardization_golden.json`:
 * an `expected.clusters` map of canonical-term IRI => list of member reference ids.
 *
 * Metrics (all in [0, 1]):
 *
 *  - PRECISION / RECALL are computed over *unordered pairs* of member ids that the
 *    gold standard says belong together (every pair within an expected cluster is a
 *    "should-link" pair). Let:
 *      - TP = should-link pairs that the produced result also places in one cluster,
 *      - FN = should-link pairs split across produced clusters (missed),
 *      - FP = pairs the produced result groups together that are NOT a gold pair.
 *    Then:
 *      - recall    = TP / (TP + FN) — fraction of expected cross-source equivalence
 *        pairs that end up in the same produced cluster,
 *      - precision = TP / (TP + FP) — fraction of produced co-membership pairs that
 *        are genuine equivalences (over-merging two distinct concepts costs precision).
 *    A pair counts only when BOTH ids are present in the golden membership universe;
 *    ids the model invented are ignored for pair scoring (the service already drops
 *    them). When there are no gold pairs at all, precision/recall default to 1.0.
 *
 *  - TERM ACCURACY = fraction of expected clusters whose single best-matching produced
 *    cluster carries the expected canonical IRI. The "best-matching" produced cluster
 *    for an expected cluster is the produced cluster sharing the most member ids with
 *    it (ties broken by first occurrence); if none overlaps, the expected cluster's
 *    term is counted as missed. When the golden set has no expected clusters, term
 *    accuracy defaults to 1.0.
 *
 * The result is pure and deterministic; no provider or network access is involved.
 */
final readonly class StandardizationEvaluator
{
    /**
     * @param array{clusters?: array<string, list<string>>} $expected the golden `expected` block
     */
    public function evaluate(array $expected, StandardizationResult $result): StandardizationEvalResult
    {
        /** @var array<string, list<string>> $expectedClusters */
        $expectedClusters = $expected['clusters'] ?? [];

        // Produced membership: list of member-id sets, plus the IRI each produced
        // cluster carries (or '' when it has no canonical term).
        $producedMembers = [];
        $producedIris = [];
        foreach ($result->clusters as $cluster) {
            $producedMembers[] = array_values(array_unique($cluster->memberRefIds));
            $producedIris[] = null !== $cluster->canonicalTerm ? $cluster->canonicalTerm->iri : '';
        }

        [$precision, $recall] = $this->pairScores($expectedClusters, $producedMembers);
        [$termAccuracy, $perConcept] = $this->termAccuracy($expectedClusters, $producedMembers, $producedIris);

        return new StandardizationEvalResult(
            precision: $precision,
            recall: $recall,
            termAccuracy: $termAccuracy,
            expectedClusterCount: \count($expectedClusters),
            producedClusterCount: \count($result->clusters),
            perConcept: $perConcept,
        );
    }

    /**
     * @param array<string, list<string>> $expectedClusters
     * @param list<list<string>>          $producedMembers
     *
     * @return array{0: float, 1: float} [precision, recall]
     */
    private function pairScores(array $expectedClusters, array $producedMembers): array
    {
        // Universe of ids that appear in the gold standard; pairs are only scored
        // over ids the gold set knows about.
        $universe = [];
        foreach ($expectedClusters as $members) {
            foreach ($members as $id) {
                $universe[$id] = true;
            }
        }

        $goldPairs = [];
        foreach ($expectedClusters as $members) {
            foreach ($this->pairsOf($members) as $pair) {
                $goldPairs[$pair] = true;
            }
        }

        $producedPairs = [];
        foreach ($producedMembers as $members) {
            // Restrict to the gold universe so invented ids never count for/against us.
            $scoped = array_values(array_filter($members, static fn (string $id): bool => isset($universe[$id])));
            foreach ($this->pairsOf($scoped) as $pair) {
                $producedPairs[$pair] = true;
            }
        }

        $tp = 0;
        foreach (array_keys($producedPairs) as $pair) {
            if (isset($goldPairs[$pair])) {
                ++$tp;
            }
        }
        $fp = \count($producedPairs) - $tp;
        $fn = \count($goldPairs) - $tp;

        $precision = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : 1.0;
        $recall = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 1.0;

        return [$precision, $recall];
    }

    /**
     * @param array<string, list<string>> $expectedClusters
     * @param list<list<string>>          $producedMembers
     * @param list<string>                $producedIris
     *
     * @return array{0: float, 1: list<ConceptScore>}
     */
    private function termAccuracy(array $expectedClusters, array $producedMembers, array $producedIris): array
    {
        if ([] === $expectedClusters) {
            return [1.0, []];
        }

        $correct = 0;
        $perConcept = [];

        foreach ($expectedClusters as $expectedIri => $members) {
            $expectedSet = array_flip($members);

            $bestIndex = null;
            $bestOverlap = 0;
            foreach ($producedMembers as $index => $producedSet) {
                $overlap = 0;
                foreach ($producedSet as $id) {
                    if (isset($expectedSet[$id])) {
                        ++$overlap;
                    }
                }
                if ($overlap > $bestOverlap) {
                    $bestOverlap = $overlap;
                    $bestIndex = $index;
                }
            }

            $producedIri = null !== $bestIndex ? $producedIris[$bestIndex] : '';
            $matched = $bestOverlap > 0 && $producedIri === (string) $expectedIri;
            if ($matched) {
                ++$correct;
            }

            $perConcept[] = new ConceptScore(
                expectedIri: (string) $expectedIri,
                producedIri: '' !== $producedIri ? $producedIri : null,
                expectedMembers: \count($members),
                overlap: $bestOverlap,
                termMatched: $matched,
            );
        }

        return [$correct / \count($expectedClusters), $perConcept];
    }

    /**
     * All unordered pairs of a member list, encoded as "a|b" with a < b for stable keys.
     *
     * @param list<string> $members
     *
     * @return list<string>
     */
    private function pairsOf(array $members): array
    {
        $members = array_values(array_unique($members));
        sort($members);

        $pairs = [];
        $count = \count($members);
        for ($i = 0; $i < $count; ++$i) {
            for ($j = $i + 1; $j < $count; ++$j) {
                $pairs[] = $members[$i] . '|' . $members[$j];
            }
        }

        return $pairs;
    }
}
