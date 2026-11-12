<?php

declare(strict_types=1);

namespace App\ConnectedApps;

use App\Enum\AppCode;

readonly class ConnectedAppServiceFactory
{
    public function __construct(
        /** @var ConnectedAppServiceInterface[] $connectedAppRepository */
        private iterable $connectedAppRepository,
    ) {
    }

    public function createService(AppCode $appCode): ConnectedAppServiceInterface
    {
        foreach ($this->connectedAppRepository as $service) {
            if ($service->supportsCode($appCode)) {
                return $service;
            }
        }

        throw new \RuntimeException(\sprintf('No connected app service found for app code: %s', $appCode->value));
    }
}
