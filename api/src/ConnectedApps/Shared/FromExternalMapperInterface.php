<?php

declare(strict_types=1);

namespace App\ConnectedApps\Shared;

use App\Entity\ConnectedAppEntityInterface;

interface FromExternalMapperInterface
{
    public function mapFromExternal(ExternalDtoInterface $dto, ConnectedAppEntityInterface $entity): void;
}
