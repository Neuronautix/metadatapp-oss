<?php

declare(strict_types=1);

namespace App\AI\Gateway;

use App\AI\Runtime\AssistantTask;

/**
 * Task-aware system prompts shared by the real provider gateways (Anthropic,
 * OpenAI). For structured tasks they describe the exact `structured_output` schema
 * the runtime parses, so a live model returns usable structured data rather than
 * free text. Keep this in sync with the parsers in ReferenceStandardizationService
 * and LlmMappingSuggestionRefiner.
 */
final class StructuredTaskPrompt
{
    private const string BASE = "You are Metadatapp's governed metadata assistant. Return ONLY a single JSON object with keys content (string), suggestions (array), and structured_output (object). Do not wrap it in markdown.";

    public static function system(string $taskType): string
    {
        return match ($taskType) {
            AssistantTask::STANDARDIZE_REFERENCES => self::BASE . ' '
                . 'The user message is JSON with `items` (reference results from different sources) and `groundedCandidates` (a map of reference id to candidate ontology terms). '
                . 'Cluster the items that describe the SAME underlying concept across sources. Set structured_output.clusters to an array of objects with: '
                . 'clusterId (string); canonicalTerm ({iri, label, ontology, confidence 0..1} chosen from the provided groundedCandidates when possible, else null); '
                . 'memberRefIds (array of item ids that belong to the cluster — use ONLY ids present in items); evidence (array of short strings); '
                . 'harmonizedFields (array of {field, value, rationale}). For harmonizedFields, reconcile each metadata field across the cluster members: pick the most standard value; when sources disagree, choose one and explain the choice in rationale. '
                . 'Return only ids that exist in items.',
            AssistantTask::COMPARE_SCHEMAS => self::BASE . ' '
                . 'The user message is JSON with `schemas` (an array of objects, each with sourceRef, sourceName, type, and fields — a list of {label, datatype, unit, sourceRef} entries). '
                . 'Group fields that represent the SAME concept across schemas into harmonized field groups. Set structured_output.fieldGroups to an array of objects with: '
                . 'groupId (string, e.g. "fg-1"); canonicalLabel (string — the most standard name for the concept); '
                . 'canonicalDatatype (string or null); canonicalUnit (string or null); confidence (float 0..1); '
                . 'members (array of {sourceRef (string matching a field sourceRef), mappingType ("exact"|"close"|"related"), label (string), evidence (string)}); '
                . 'conflicts (array of {field, description} when sources disagree on datatype or unit). '
                . 'Only use sourceRef values present in the input. Fields with no cross-schema match should appear as singleton groups.',
            AssistantTask::REFINE_CROSSWALK_MAPPING => self::BASE . ' '
                . 'The user message is JSON with `field` and `candidates` (each has a numeric `ref`). '
                . 'Re-rank the candidates best-first and set structured_output.rankings to an array of {ref (integer matching a candidate), confidence (0..1), evidence (array of short strings)}. Use only refs present in candidates.',
            AssistantTask::DRAFT_GUIDELINE_FIELD => self::BASE . ' '
                . 'You are drafting a single field of an assigned reporting/planning standard (named in the user message as `template`). The approach is standard-agnostic: rely ONLY on the provided `field` payload — its `label`, the `*_section` context, `severity`, the planning `prompt` or `guidance`, the `context_keys` (sibling field ids) and `context_values` (existing values of filled context keys) — and on the provided `evidence` array (each item is {source, label, value, provenance}, deterministically derived from the lab\'s real data). '
                . 'Ground the value ONLY in that evidence + crosswalk context; never fabricate facts, numbers, counts, dates or identifiers that are not present there. '
                . 'Set structured_output to {value (string — a single grounded draft for this field, written in plain prose), rationale (string — one short sentence that CITES which evidence item(s)/provenance the draft was grounded in)}. '
                . 'If the evidence and context are insufficient to ground a value, set value to an empty string and say so in rationale.',
            default => self::BASE . ' suggestions must be an array.',
        };
    }

    /**
     * Structured clustering/harmonization needs more room than a chat reply.
     */
    public static function maxTokens(string $taskType): int
    {
        return \in_array($taskType, [AssistantTask::STANDARDIZE_REFERENCES, AssistantTask::COMPARE_SCHEMAS], true) ? 4096 : 1200;
    }
}
