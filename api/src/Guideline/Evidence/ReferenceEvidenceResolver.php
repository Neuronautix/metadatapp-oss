<?php

declare(strict_types=1);

namespace App\Guideline\Evidence;

use App\ConnectedApps\Apps\GuidelinesHub\GuidelinesHubDataProvider;
use App\Guideline\Schema\RequiredMetadata;

/**
 * Deterministic citation source: maps a guideline field onto the canonical
 * standard text + official URL published by the bundled GuidelinesHub catalogue
 * (ARRIVE 2.0, PREPARE, EQIPD).
 *
 * This is PUBLIC standard text — not account data — so it is tenant-agnostic and
 * requires no model call. The single best-matching catalogue item for a field is
 * found by DETERMINISTIC token-overlap of the field's label/section against each
 * item's "title + description", restricted to the field's own standard (derived
 * from its id prefix + which `*_section` is populated). Even when no item is a
 * strong match, the standard's canonical landing URL is still attached at a low
 * confidence so the field always links to the published standard.
 *
 * The catalogue is materialised ONCE per resolver instance (the provider is
 * static/bundled — no external calls) and reused across every field lookup.
 */
final class ReferenceEvidenceResolver
{
    private const int MAX_VALUE_LENGTH = 500;

    /** Below this overlap score we treat the match as "weak" and only cite the standard URL. */
    private const float MATCH_THRESHOLD = 0.2;

    /**
     * Materialised catalogue, grouped by source ('arrive'|'prepare'|'eqipd').
     *
     * @var array<string, list<array{id: string, source: string, sourceLabel: string, title: string, description: string, url: string}>>|null
     */
    private ?array $itemsBySource = null;

    /**
     * Canonical landing URL per source, captured from the catalogue (no hardcoding).
     *
     * @var array<string, array{url: string, label: string}>|null
     */
    private ?array $sourceMeta = null;

    public function __construct(
        private readonly GuidelinesHubDataProvider $provider,
    ) {
    }

    /**
     * The reference EvidenceItem(s) for one field: at most one item — the best
     * matching standard entry, or (on a weak/absent match) the standard's
     * canonical landing URL at a low confidence. Never throws; returns [] only
     * when the field maps to no covered standard.
     *
     * @return list<EvidenceItem>
     */
    public function forField(RequiredMetadata $field): array
    {
        $source = $this->standardForField($field);
        $items = $this->itemsForSource($source);
        if ([] === $items) {
            return [];
        }

        $needle = $this->fieldTokens($field);

        $best = null;
        $bestScore = 0.0;
        foreach ($items as $item) {
            $score = $this->overlap($needle, $this->tokenize($item['title'] . ' ' . $item['description']));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $item;
            }
        }

        if (null !== $best && $bestScore >= self::MATCH_THRESHOLD) {
            return [new EvidenceItem(
                fieldId: $field->id,
                source: EvidenceItem::SOURCE_REFERENCE,
                label: $this->sanitize(\sprintf('%s — %s', $best['sourceLabel'], $best['title']), 160),
                value: $this->sanitize($best['description']),
                provenance: $this->sanitize(\sprintf('%s reference (%s)', $best['sourceLabel'], $this->host($best['url'])), 160),
                confidence: round(min(1.0, $bestScore), 2),
                url: $best['url'],
            )];
        }

        // Weak/no match: still link the field to the published standard.
        $meta = $this->sourceMeta()[$source] ?? null;
        if (null === $meta) {
            return [];
        }

        return [new EvidenceItem(
            fieldId: $field->id,
            source: EvidenceItem::SOURCE_REFERENCE,
            label: $this->sanitize(\sprintf('%s standard', $meta['label']), 160),
            value: $this->sanitize(\sprintf('Refer to the %s reporting standard.', $meta['label'])),
            provenance: $this->sanitize(\sprintf('%s reference (%s)', $meta['label'], $this->host($meta['url'])), 160),
            confidence: 0.1,
            url: $meta['url'],
        )];
    }

    /**
     * Map a field to its standard ('arrive'|'prepare'|'eqipd') via the id-prefix
     * convention reinforced by which `*_section` key is populated (§2). MNMS is
     * intentionally not covered: it falls through to ARRIVE only when an arrive
     * section is present, otherwise to whichever section the field declares.
     */
    private function standardForField(RequiredMetadata $field): string
    {
        if (str_starts_with($field->id, 'prepare_') || (null !== $field->prepareSection && null === $field->arriveSection && null === $field->eqipdSection)) {
            return 'prepare';
        }
        if (str_starts_with($field->id, 'eqipd_') || (null !== $field->eqipdSection && null === $field->arriveSection && null === $field->prepareSection)) {
            return 'eqipd';
        }

        return 'arrive';
    }

    /**
     * @return list<array{id: string, source: string, sourceLabel: string, title: string, description: string, url: string}>
     */
    private function itemsForSource(string $source): array
    {
        if (null === $this->itemsBySource) {
            $this->materialise();
        }

        /** @var array<string, list<array{id: string, source: string, sourceLabel: string, title: string, description: string, url: string}>> $grouped */
        $grouped = $this->itemsBySource ?? [];

        return $grouped[$source] ?? [];
    }

    /**
     * @return array<string, array{url: string, label: string}>
     */
    private function sourceMeta(): array
    {
        if (null === $this->sourceMeta) {
            $this->materialise();
        }

        return $this->sourceMeta ?? [];
    }

    /**
     * Materialise the bundled catalogue ONCE (empty query => every item, no
     * external call) and index it by source for cheap per-field lookups.
     */
    private function materialise(): void
    {
        $grouped = [];
        $meta = [];
        foreach ($this->provider->search('', \PHP_INT_MAX) as $item) {
            $grouped[$item['source']][] = $item;
            // First-seen url/label per source is the canonical landing for that standard.
            $meta[$item['source']] ??= ['url' => $item['url'], 'label' => $item['sourceLabel']];
        }

        $this->itemsBySource = $grouped;
        $this->sourceMeta = $meta;
    }

    private function fieldTokens(RequiredMetadata $field): string
    {
        $label = str_replace(['prepare_', 'eqipd_', '_'], ['', '', ' '], $field->id);

        return $this->normalise(
            $label . ' '
            . (string) $field->arriveSection . ' '
            . (string) $field->prepareSection . ' '
            . (string) $field->eqipdSection . ' '
            . (string) $field->guidance,
        );
    }

    /**
     * Deterministic token-overlap score in [0,1]: |needle ∩ hay| / |needle|.
     *
     * @param string $needle normalised whitespace-joined tokens
     * @param string $hay    normalised whitespace-joined tokens
     */
    private function overlap(string $needle, string $hay): float
    {
        $needleTokens = $this->tokens($needle);
        if ([] === $needleTokens) {
            return 0.0;
        }
        $hayTokens = array_fill_keys($this->tokens($hay), true);

        $hits = 0;
        foreach ($needleTokens as $token) {
            if (isset($hayTokens[$token])) {
                ++$hits;
            }
        }

        return $hits / \count($needleTokens);
    }

    private function tokenize(string $value): string
    {
        return $this->normalise($value);
    }

    private function normalise(string $value): string
    {
        $lower = mb_strtolower($value);
        $clean = preg_replace('/[^a-z0-9]+/u', ' ', $lower) ?? $lower;

        return trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);
    }

    /**
     * Distinct, stop-word-free tokens (length >= 3) from a normalised string.
     *
     * @return list<string>
     */
    private function tokens(string $normalised): array
    {
        $stop = array_fill_keys([
            'the', 'and', 'for', 'with', 'any', 'each', 'are', 'was', 'this', 'that', 'used', 'use',
            'all', 'from', 'into', 'per', 'their', 'they', 'how', 'whether', 'which', 'study', 'data',
        ], true);

        $out = [];
        foreach (explode(' ', $normalised) as $token) {
            if (mb_strlen($token) < 3 || isset($stop[$token]) || isset($out[$token])) {
                continue;
            }
            $out[$token] = true;
        }

        return array_keys($out);
    }

    private function host(string $url): string
    {
        $host = parse_url($url, \PHP_URL_HOST);

        return \is_string($host) ? $host : $url;
    }

    /**
     * Length-bound + strip control/role-marker characters so the text is safe to
     * embed in an LLM prompt (mirrors the retriever's defence).
     */
    private function sanitize(string $value, int $maxLength = self::MAX_VALUE_LENGTH): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $clean = preg_replace('/(?i)\b(system|assistant|user)\s*:/', '$1 -', $clean) ?? $clean;
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);

        if (mb_strlen($clean) > $maxLength) {
            $clean = mb_substr($clean, 0, $maxLength - 1) . '…';
        }

        return $clean;
    }
}
