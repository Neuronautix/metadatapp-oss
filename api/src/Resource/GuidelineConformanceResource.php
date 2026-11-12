<?php

declare(strict_types=1);

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\Provider\GuidelineConformanceProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Read-only conformance report (§6.1/§6.3) for a Study or Investigation against
 * an assigned guideline template.
 *
 *  GET /studies/{id}/guidelines/{templateId}/conformance
 *  GET /investigations/{id}/guidelines/{templateId}/conformance
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/studies/{id}/guidelines/{templateId}/conformance',
            uriVariables: ['id', 'templateId'],
            provider: GuidelineConformanceProvider::class,
            description: 'Conformance report for a study against a guideline template.',
        ),
        new Get(
            uriTemplate: '/investigations/{id}/guidelines/{templateId}/conformance',
            uriVariables: ['id', 'templateId'],
            provider: GuidelineConformanceProvider::class,
            description: 'Conformance report for an investigation against a guideline template.',
        ),
    ],
    normalizationContext: ['groups' => ['guideline_conformance:read']],
    security: "is_granted('ROLE_USER')",
)]
final class GuidelineConformanceResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['guideline_conformance:read'])]
    public string $id = '';

    #[Groups(['guideline_conformance:read'])]
    public string $resourceType = '';

    #[Groups(['guideline_conformance:read'])]
    public string $templateId = '';

    #[Groups(['guideline_conformance:read'])]
    public string $standard = '';

    #[Groups(['guideline_conformance:read'])]
    public string $version = '';

    /** @var array{dimension: string, points: int, max_points: int, satisfied_pct: float} */
    #[Groups(['guideline_conformance:read'])]
    public array $score = ['dimension' => 'R', 'points' => 0, 'max_points' => 5, 'satisfied_pct' => 0.0];

    /** @var array{satisfied: int, total: int} */
    #[Groups(['guideline_conformance:read'])]
    public array $totals = ['satisfied' => 0, 'total' => 0];

    /** @var list<array<string, mixed>> */
    #[Groups(['guideline_conformance:read'])]
    public array $entries = [];
}
