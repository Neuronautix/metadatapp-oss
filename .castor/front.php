<?php

namespace qa\osoma;

use Castor\Attribute\AsTask;
use function docker\docker_exit_code;

#[AsTask(description: 'Runs Osoma build', namespace: 'qa:osoma', name: 'build')]
function build(): int
{
    return docker_exit_code('COREPACK_ENABLE_DOWNLOAD_PROMPT=0 pnpm install --frozen-lockfile && pnpm build', service: 'osoma');
}
