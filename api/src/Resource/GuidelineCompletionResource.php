<?php

declare(strict_types=1);

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Provider\GuidelineCompletionProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Read-only completion roll-up (§8.2) for the fill workspace.
 *
 *  GET /studies/{id}/guidelines/{templateId}/completion
 *  GET /investigations/{id}/guidelines/{templateId}/completion
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/studies/{id}/guidelines/{templateId}/completion',
            uriVariables: ['id', 'templateId'],
            provider: GuidelineCompletionProvider::class,
            description: 'Completion roll-up for a study against a guideline template.',
        ),
        new Get(
            uriTemplate: '/investigations/{id}/guidelines/{templateId}/completion',
            uriVariables: ['id', 'templateId'],
            provider: GuidelineCompletionProvider::class,
            description: 'Completion roll-up for an investigation against a guideline template.',
        ),
    ],
    normalizationContext: ['groups' => ['guideline_completion:read']],
    security: "is_granted('ROLE_USER')",
)]
final class GuidelineCompletionResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['guideline_completion:read'])]
    public string $id = '';

    #[Groups(['guideline_completion:read'])]
    public string $resourceType = '';

    #[Groups(['guideline_completion:read'])]
    public string $templateId = '';

    #[Groups(['guideline_completion:read'])]
    public string $standard = '';

    /** @var array{satisfied_direct: int, satisfied_via_crosswalk: int, partial: int, missing: int} */
    #[Groups(['guideline_completion:read'])]
    public array $totals = ['satisfied_direct' => 0, 'satisfied_via_crosswalk' => 0, 'partial' => 0, 'missing' => 0];

    /** @var array<string, array{satisfied: int, partial: int, missing: int}> */
    #[Groups(['guideline_completion:read'])]
    public array $bySeverity = [];

    /** @var array<string, array{satisfied: int, partial: int, missing: int, fields: list<string>}> */
    #[Groups(['guideline_completion:read'])]
    public array $bySection = [];

    /** @var list<array<string, mixed>> */
    #[Groups(['guideline_completion:read'])]
    public array $fields = [];

    /** @var array{status: string, reviewed_by: string|null, reviewed_at: string|null, note?: string|null} */
    #[Groups(['guideline_completion:read'])]
    public array $reportReview = ['status' => 'not_reviewed', 'reviewed_by' => null, 'reviewed_at' => null];
}
