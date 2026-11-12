<?php

namespace declarations;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;
use function Castor\variable;

/**
 * Generate (or check) the machine- and human-readable repository declarations
 * (FAIR.md, TRUST.md, AI-declaration.md, the design declaration, llms.txt and
 * the .well-known index) from evidence found in the repo.
 */
#[AsTask(description: 'Generates repository declarations (FAIR/TRUST/AI/design)', namespace: 'declarations', name: 'generate', aliases: ['declarations'])]
function generate(
    #[AsOption(description: 'Only verify declarations are up to date (CI mode)')]
    bool $check = false,
    #[AsOption(description: 'List detected declarations and their evidence')]
    bool $list = false,
): int {
    $args = [];
    if ($check) {
        io()->section('Checking repository declarations are up to date...');
        $args[] = '--check';
    } elseif ($list) {
        $args[] = '--list';
    } else {
        io()->section('Generating repository declarations...');
    }

    $process = run(
        array_merge(['python3', variable('root_dir') . '/scripts/generate-declarations.py'], $args),
        context: context()->withAllowFailure(),
    );

    return $process->getExitCode() ?? 0;
}
