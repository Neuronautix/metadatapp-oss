<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\NihCde\Client;

use App\Entity\ConnectedApp;

interface NihCdeClientInterface
{
    public function getBaseUrl(ConnectedApp $connectedApp): string;

    /**
     * @return array{totalItems: int, elements: list<array<string, mixed>>}
     */
    public function search(ConnectedApp $connectedApp, string $searchTerm, int $resultPerPage = 20, int $skip = 0): array;

    /**
     * @return array<string, mixed>
     */
    public function getElement(ConnectedApp $connectedApp, string $tinyId): array;

    /**
     * @return array{totalItems?: int, forms: list<array<string, mixed>>}
     */
    public function searchForms(ConnectedApp $connectedApp, string $searchTerm, int $resultPerPage = 20, int $skip = 0): array;
}
