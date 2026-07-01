<?php

declare(strict_types=1);

namespace App\ConnectedApps\Shared;

use App\Entity\ConnectedAppEntityInterface;

interface ToExternalMapperInterface
{
    public function mapToExternal(ConnectedAppEntityInterface $entity): ExternalDtoInterface;
}
