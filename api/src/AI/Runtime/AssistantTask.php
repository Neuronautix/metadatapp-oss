<?php

declare(strict_types=1);

namespace App\AI\Runtime;

final class AssistantTask
{
    public const STATUS = 'assistant_status';
    public const CHAT = 'assistant_chat';
    public const SUGGEST_FORM_INPUTS = 'suggest_form_inputs';
    public const EXPLAIN_MISSING_METADATA = 'explain_missing_metadata';
    public const DRAFT_QUERY_FILTERS = 'draft_query_filters';
    public const GENERATE_EXPORT_CONTENT = 'generate_export_content';
    public const STANDARDIZE_REFERENCES = 'standardize_references';
    public const REFINE_CROSSWALK_MAPPING = 'refine_crosswalk_mapping';
    public const DRAFT_GUIDELINE_FIELD = 'draft_guideline_field';
    public const AGENTIC_CHAT = 'agentic_chat';
    public const COMPARE_SCHEMAS = 'compare_schemas';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::STATUS,
            self::CHAT,
            self::SUGGEST_FORM_INPUTS,
            self::EXPLAIN_MISSING_METADATA,
            self::DRAFT_QUERY_FILTERS,
            self::GENERATE_EXPORT_CONTENT,
            self::STANDARDIZE_REFERENCES,
            self::REFINE_CROSSWALK_MAPPING,
            self::DRAFT_GUIDELINE_FIELD,
            self::AGENTIC_CHAT,
            self::COMPARE_SCHEMAS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function interactiveCapabilities(): array
    {
        return [
            self::CHAT,
            self::SUGGEST_FORM_INPUTS,
            self::EXPLAIN_MISSING_METADATA,
            self::DRAFT_QUERY_FILTERS,
            self::GENERATE_EXPORT_CONTENT,
        ];
    }
}
