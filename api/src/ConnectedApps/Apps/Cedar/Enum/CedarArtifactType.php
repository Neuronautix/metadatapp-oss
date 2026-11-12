<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Cedar\Enum;

/**
 * The four CEDAR artifact kinds the resource server (and the official
 * `cedar-artifact-rest-mcp`) manage. Mirrors that MCP's `ArtifactType` enum so
 * Metadatapp speaks the exact same REST contract: the resource-server path
 * segment, the tool-name noun, and the `resource_type` the server's validate
 * command expects.
 *
 * @see https://github.com/metadatacenter/cedar-artifact-rest-mcp
 */
enum CedarArtifactType: string
{
    case Template = 'template';
    case Element = 'element';
    case Field = 'field';
    case Instance = 'instance';

    /**
     * REST path segment, e.g. `templates` in `/templates/{id}`.
     */
    public function pathSegment(): string
    {
        return match ($this) {
            self::Template => 'templates',
            self::Element => 'template-elements',
            self::Field => 'template-fields',
            self::Instance => 'template-instances',
        };
    }

    /**
     * The `resource_type` query value for `POST /command/validate`.
     */
    public function validateResourceType(): string
    {
        return $this->value;
    }

    /**
     * Resolve the artifact kind from the noun used in the public API/UI
     * (`template`, `element`, `field`, `instance`).
     */
    public static function fromNoun(string $noun): self
    {
        return self::from(strtolower(trim($noun)));
    }

    /**
     * Best-effort detection of an artifact's kind from its JSON-LD type
     * discriminator (falling back to `schema:isBasedOn` for instances). Returns
     * null when the kind cannot be determined so the caller can ask explicitly.
     *
     * @param array<int|string, mixed> $artifact
     */
    public static function detect(array $artifact): ?self
    {
        $rawType = $artifact['@type'] ?? null;
        $types = match (true) {
            \is_array($rawType) => $rawType,
            null === $rawType => [],
            default => [$rawType],
        };

        foreach ($types as $type) {
            if (!\is_string($type)) {
                continue;
            }

            // Order matters: `TemplateElement` / `TemplateField` both contain
            // the substring `Template`, so they must be checked first.
            if (str_contains($type, 'TemplateElement')) {
                return self::Element;
            }
            if (str_contains($type, 'TemplateField')) {
                return self::Field;
            }
            if (str_contains($type, '/core/Template')) {
                return self::Template;
            }
        }

        if (\array_key_exists('schema:isBasedOn', $artifact)) {
            return self::Instance;
        }

        return null;
    }
}
