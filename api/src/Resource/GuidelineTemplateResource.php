<?php

declare(strict_types=1);

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\Provider\GuidelineTemplateProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Read-only resource exposing the guideline template catalogue and resolved
 * (merged) templates.
 *
 *  GET /guidelines/templates              → list (id, name, version, counts)
 *  GET /guidelines/templates/{templateId} → resolved merged template
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/guidelines/templates',
            provider: GuidelineTemplateProvider::class,
            description: 'List the available guideline templates.',
        ),
        new Get(
            uriTemplate: '/guidelines/templates/{templateId}',
            uriVariables: ['templateId'],
            provider: GuidelineTemplateProvider::class,
            description: 'Get a resolved (merged) guideline template.',
        ),
    ],
    normalizationContext: ['groups' => ['guideline_template:read']],
    security: "is_granted('ROLE_USER')",
)]
final class GuidelineTemplateResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['guideline_template:read'])]
    public string $templateId = '';

    #[Groups(['guideline_template:read'])]
    public string $name = '';

    #[Groups(['guideline_template:read'])]
    public string $version = '';

    #[Groups(['guideline_template:read'])]
    public string $description = '';

    #[Groups(['guideline_template:read'])]
    public ?string $identifier = null;

    /** @var list<string> */
    #[Groups(['guideline_template:read'])]
    public array $conformsTo = [];

    #[Groups(['guideline_template:read'])]
    public int $requiredColumnCount = 0;

    #[Groups(['guideline_template:read'])]
    public int $optionalColumnCount = 0;

    #[Groups(['guideline_template:read'])]
    public int $requiredMetadataCount = 0;

    /**
     * Resolved merged fields — only populated on the item (Get) operation.
     *
     * @var list<array<string, mixed>>
     */
    #[Groups(['guideline_template:read'])]
    public array $requiredColumns = [];

    /** @var list<array<string, mixed>> */
    #[Groups(['guideline_template:read'])]
    public array $optionalColumns = [];

    /** @var list<array<string, mixed>> */
    #[Groups(['guideline_template:read'])]
    public array $requiredMetadata = [];

    /** @var array{dimension: string, max_points: int}|null */
    #[Groups(['guideline_template:read'])]
    public ?array $fairBonus = null;
}
