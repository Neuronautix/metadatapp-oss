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
