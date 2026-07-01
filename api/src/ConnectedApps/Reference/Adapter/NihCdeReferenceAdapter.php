<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\NihCde\Client\NihCdeClientInterface;
use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchAdapterInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;

/**
 * Maps NIH Common Data Element (CDE) repository elements (free-text search) into
 * reference results.
 */
final readonly class NihCdeReferenceAdapter implements ReferenceSearchAdapterInterface
{
    private const string PUBLIC_SITE = 'https://cde.nlm.nih.gov';

    public function __construct(private NihCdeClientInterface $client)
    {
    }

    public function supports(AppCode $code): bool
    {
        return AppCode::NihCde === $code;
    }

    public function search(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        $results = [];
        $payload = $this->client->search($connectedApp, $query, $limit, 0);

        foreach ($payload['elements'] as $element) {
            $tinyId = $element['tinyId'] ?? null;
            if (null === $tinyId) {
                continue;
            }
            $results[] = new ReferenceResult(
                id: 'nih_cde:cde_element:' . $tinyId,
                source: AppCode::NihCde->value,
                sourceName: $connectedApp->getName(),
                type: 'cde_element',
                title: $this->firstDesignation($element) ?? (string) $tinyId,
                subtitle: $this->stewardName($element),
                description: $this->firstDefinition($element),
                externalUrl: self::PUBLIC_SITE . '/deView?tinyId=' . rawurlencode((string) $tinyId),
                identifiers: array_filter([
                    'tinyId' => $tinyId,
                    'version' => $element['version'] ?? null,
                ], static fn ($v): bool => null !== $v),
                raw: $element,
            );
        }

        // Also search CDE forms
        $formPayload = $this->client->searchForms($connectedApp, $query, $limit, 0);
        foreach ($formPayload['forms'] as $form) {
            $tinyId = $form['tinyId'] ?? null;
            if (null === $tinyId) {
                continue;
            }
            $results[] = new ReferenceResult(
                id: 'nih_cde:template:' . $tinyId,
                source: AppCode::NihCde->value,
                sourceName: $connectedApp->getName(),
                type: 'template',
                title: $this->firstDesignation($form) ?? (string) $tinyId,
                subtitle: $this->stewardName($form),
                description: \count($form['formElements'] ?? []) . ' elements',
                externalUrl: self::PUBLIC_SITE . '/formView?tinyId=' . rawurlencode((string) $tinyId),
                identifiers: array_filter([
                    'tinyId' => $tinyId,
                    'version' => $form['version'] ?? null,
                ], static fn ($v): bool => null !== $v),
                raw: $form,
            );
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $element
     */
    private function firstDesignation(array $element): ?string
    {
        $designation = $element['designations'][0]['designation'] ?? null;

        return \is_string($designation) && '' !== $designation ? $designation : null;
    }

    /**
     * @param array<string, mixed> $element
     */
    private function firstDefinition(array $element): ?string
    {
        $definition = $element['definitions'][0]['definition'] ?? null;

        return \is_string($definition) && '' !== $definition ? $definition : null;
    }

    /**
     * @param array<string, mixed> $element
     */
    private function stewardName(array $element): ?string
    {
        $name = $element['stewardOrg']['name'] ?? null;

        return \is_string($name) && '' !== $name ? $name : null;
    }
}
