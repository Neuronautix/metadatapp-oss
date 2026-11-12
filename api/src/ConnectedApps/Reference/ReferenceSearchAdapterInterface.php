<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference;

use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Wraps one connected app's existing search/list client into the normalized
 * Reference Hub result shape. Implementations are auto-tagged and collected by
 * {@see ReferenceSearchService}.
 */
#[AutoconfigureTag('app.reference_search_adapter')]
interface ReferenceSearchAdapterInterface
{
    public function supports(AppCode $code): bool;

    /**
     * Run the app's search for $query and map hits to ReferenceResults. Must not
     * throw for "no results"; may throw on upstream failure (the service isolates
     * each adapter so one failure never breaks the federated search).
     *
     * @return list<ReferenceResult>
     */
    public function search(ConnectedApp $connectedApp, string $query, int $limit): array;
}
