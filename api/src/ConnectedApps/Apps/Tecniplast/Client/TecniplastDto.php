<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Tecniplast\Client;

use App\ConnectedApps\Shared\ExternalDtoInterface;

class TecniplastDto implements ExternalDtoInterface
{
    public function __construct(
        public array $data,
    ) {
    }
}
