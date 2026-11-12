<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Tecniplast\Mapper;

use App\ConnectedApps\Apps\Tecniplast\Client\TecniplastDto;
use App\ConnectedApps\Shared\ExternalDtoInterface;
use App\ConnectedApps\Shared\FromExternalMapperInterface;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\Variable;

class MetricMapper implements FromExternalMapperInterface
{
    /**
     * @param TecniplastDto $dto
     * @param Variable      $entity
     */
    public function mapFromExternal(ExternalDtoInterface $dto, ConnectedAppEntityInterface $entity): void
    {
        $data = $dto->data;
        // Mapping Tecniplast Metric to Variable
        $entity->setName($data['description'] ?? 'Tecniplast Metric');
        if (isset($data['id'])) {
            $entity->setTecniplastExternalId($data['id']);
        }
    }
}
