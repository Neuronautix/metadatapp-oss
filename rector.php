<?php

declare(strict_types=1);

require_once __DIR__ . '/api/vendor/autoload.php';

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/api/src/DataFixtures',
        __DIR__ . '/api/tests',
    ])
    ->withSets([
        __DIR__ . '/api/vendor/zenstruck/foundry/utils/rector/config/foundry-2.7.php',
    ])
;
