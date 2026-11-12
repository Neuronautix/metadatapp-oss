<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Security\Roles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves feature-flag overrides derived from the logged-in user's account preset.
 *
 * The frontend FeatureFlagProvider calls GET /api/feature-flags/overrides on every
 * page load. The Osoma API proxy strips the /api prefix before forwarding the
 * request to this backend route.
 *
 * POST /api/feature-flags/overrides and POST /api/feature-flags/presets are accepted
 * and no-op'd when an account-level preset is active (the frontend should honour the
 * `enforced` flag in the response and disable the Flag Studio for those users).
 */
#[Route('/feature-flags', name: 'api_feature_flags_')]
#[IsGranted(Roles::ROLE_USER)]
final class FeatureFlagController extends AbstractController
{
    /**
     * Maps preset keys to their flag overrides.
     *
     * Keys must match the FeatureFlagKey union type on the frontend.
     * Only flags that differ from the compiled defaults need to be listed;
     * the frontend merges these on top of featureFlagRegistry defaults.
     *
     * @return array<string, array<string, bool>>
     */
    private const array PRESET_OVERRIDES = [
        'zefix' => [
            'metadatapp-feat.enabled' => false,
            'non-metadatapp-feat.enabled' => false,
            'zefix.enabled' => true,
            'atmp.enabled' => false,
            'dvc.observatory.enabled' => false,
            'dvc.namVcg.enabled' => false,
            'dvc.aiInsights.enabled' => false,
            'dvc.studyal.welfare.enabled' => false,
            'dvc.export.enabled' => false,
            'dvc.circadian.enabled' => false,
            'namVcg.meanTrace.enabled' => false,
            'cages.enabled' => false,
            'studyal.agentic' => false,
            'studyal.explainability' => false,
            'studyal.virtualControl' => false,
            'studyal.narrative' => false,
            'studyal.timeTravelLab' => false,
            'feature.observatory' => false,
            'feature.auditLogs' => false,
            'feature.future' => false,
            'feature.connectedApps' => false,
            'feature.connectedApps.deepDive' => false,
            'import.enabled' => false,
            'dataEntry.enabled' => false,
            'feature.curationWorkflow.enabled' => false,
            'feature.curateGpt.enabled' => false,
        ],
        'tecniplast' => [
            'metadatapp-feat.enabled' => true,
            'non-metadatapp-feat.enabled' => true,
            'zefix.enabled' => false,
            'atmp.enabled' => false,
            'dvc.observatory.enabled' => false,
            'dvc.namVcg.enabled' => false,
            'dvc.aiInsights.enabled' => false,
            'dvc.studyal.welfare.enabled' => false,
            'dvc.export.enabled' => false,
            'dvc.circadian.enabled' => true,
            'namVcg.meanTrace.enabled' => false,
            'cages.enabled' => true,
            'studyal.agentic' => false,
            'studyal.explainability' => false,
            'studyal.virtualControl' => false,
            'studyal.narrative' => false,
            'studyal.timeTravelLab' => false,
            'feature.observatory' => false,
            'feature.auditLogs' => false,
            'feature.future' => false,
            'feature.connectedApps' => true,
            'feature.connectedApps.deepDive' => true,
            'import.enabled' => true,
            'dataEntry.enabled' => false,
            'feature.curationWorkflow.enabled' => false,
            'feature.curateGpt.enabled' => false,
        ],
        'god' => [],
    ];

    /**
     * Returns the feature-flag overrides for the logged-in user's account preset.
     * When the account has no preset, returns an empty overrides list so the frontend
     * falls back to its compiled defaults and localStorage overrides.
     */
    #[Route('/overrides', name: 'overrides_get', methods: ['GET'])]
    public function getOverrides(): JsonResponse
    {
        $preset = $this->resolveAccountPreset();

        if (null === $preset) {
            return $this->json(['overrides' => [], 'preset' => null, 'enforced' => false]);
        }

        $flagMap = self::PRESET_OVERRIDES[$preset] ?? [];
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $overrides = [];

        foreach ($flagMap as $key => $enabled) {
            $overrides[] = [
                'key' => $key,
                'enabled' => $enabled,
                'updatedAt' => $now,
                'updatedBy' => \sprintf('Account preset: %s', $preset),
            ];
        }

        return $this->json([
            'overrides' => $overrides,
            'preset' => $preset,
            'enforced' => true,
        ]);
    }

    /**
     * Accepts a single flag override from the Flag Studio.
     * When the account has an enforced preset the request is silently accepted
     * but not applied — the frontend is expected to honour the `enforced` flag
     * returned by GET /overrides and disable manual edits for those users.
     */
    #[Route('/overrides', name: 'overrides_post', methods: ['POST'])]
    public function postOverride(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) $request->toArray();

        $key = isset($payload['key']) && \is_string($payload['key']) ? $payload['key'] : null;
        if (null === $key) {
            return $this->json(['message' => 'Invalid feature flag key'], 400);
        }

        if (null !== $this->resolveAccountPreset()) {
            // Account-level preset takes precedence; discard manual overrides.
            return $this->json(['override' => null, 'enforced' => true]);
        }

        $override = [
            'key' => $key,
            'enabled' => (bool) ($payload['enabled'] ?? false),
            'updatedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'updatedBy' => isset($payload['updatedBy']) && \is_string($payload['updatedBy'])
                ? $payload['updatedBy']
                : 'Feature Flag Studio',
        ];

        return $this->json(['override' => $override]);
    }

    /**
     * Accepts a preset application request.
     * When the account has an enforced preset the request is silently discarded.
     */
    #[Route('/presets', name: 'presets_post', methods: ['POST'])]
    public function postPreset(Request $request): JsonResponse
    {
        if (null !== $this->resolveAccountPreset()) {
            return $this->json(['overrides' => [], 'enforced' => true]);
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) $request->toArray();
        $overrides = isset($payload['overrides']) && \is_array($payload['overrides'])
            ? $payload['overrides']
            : [];

        return $this->json(['overrides' => $overrides]);
    }

    private function resolveAccountPreset(): ?string
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $preset = $user->getAccount()->getFeaturePreset();

        return isset(self::PRESET_OVERRIDES[$preset]) ? $preset : null;
    }
}
