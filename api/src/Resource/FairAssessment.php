<?php

declare(strict_types=1);

namespace App\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\McpTool;
use App\State\Provider\FairAssessmentProvider;

/**
 * Virtual read-only resource exposing a full FAIR assessment for a Study (Experiment).
 *
 * Accessible at: GET /studies/{id}/fair-assessment
 *
 * Exposed as an MCP tool named `get_fair_assessment` so that the AI assistant can
 * retrieve FAIR scores and per-criterion rationale for any study by UUID.
 */
#[McpTool(
    name: 'get_fair_assessment',
    description: 'Retrieve the complete FAIR (Findable, Accessible, Interoperable, Reusable) assessment for a study (experiment) by its UUID. Returns overall score, per-pillar scores, and all 15 sub-criteria (F1-F4, A1-A2, I1-I3, R1-R1.3) with pass/fail status and explanatory notes.',
)]
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/studies/{id}/fair-assessment',
            provider: FairAssessmentProvider::class,
            description: 'Get the FAIR assessment for a study.',
        ),
    ],
    normalizationContext: ['groups' => ['fair_assessment:read']],
    security: "is_granted('ROLE_USER')",
)]
final class FairAssessment
{
    #[ApiProperty(identifier: true)]
    public string $id = '';

    /** Name of the assessed study. */
    public string $studyName = '';

    /** Name of the parent investigation. */
    public string $investigationName = '';

    /**
     * Aggregated FAIR score (0–100).
     *
     * @var array{total: int, findable: int, accessible: int, interoperable: int, reusable: int}
     */
    public array $score = [
        'total' => 0,
        'findable' => 0,
        'accessible' => 0,
        'interoperable' => 0,
        'reusable' => 0,
    ];

    /**
     * All 15 FAIR sub-criteria grouped by pillar.
     *
     * @var array<string, list<array{id: string, label: string, description: string, pass: bool, note: string}>>
     */
    public array $criteria = [];

    /** ISO-8601 timestamp (UTC) at which this assessment was computed. */
    public string $assessedAt = '';
}
