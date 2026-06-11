<?php

namespace qa;

// use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;
use function Castor\variable;
use function docker\docker_compose_run;
use function docker\docker_exit_code;

#[AsTask(description: 'Runs all QA tasks')]
function all(): int
{
    install();
    $agentDocs = agent_docs();
    $cs = cs();
    $phpstan = phpstan();
    $phpunit = phpunit();
    $schemaValidate = schema_validate();

    return max($agentDocs, $cs, $phpstan, $phpunit, $schemaValidate);
}

#[AsTask(description: 'Installs tooling')]
function install(): void
{
    io()->title('Installing QA tooling');

    docker_compose_run('composer install -o', workDir: '/var/www/tools/php-cs-fixer');
    docker_compose_run('composer install -o', workDir: '/var/www/tools/phpstan');
}

#[AsTask(description: 'Updates tooling')]
function update(): void
{
    io()->title('Updating QA tooling');

    docker_compose_run('composer update -o', workDir: '/var/www/tools/php-cs-fixer');
    docker_compose_run('composer update -o', workDir: '/var/www/tools/phpstan');
}

/**
 * @param string[] $rawTokens
 */
#[AsTask(description: 'Runs PHPUnit', aliases: ['phpunit', 'tests'])]
function phpunit(#[AsRawTokens] array $rawTokens = []): int
{
    io()->section('Preparing PHPUnit test database...');

    docker_compose_run(
        'bin/console -e test doctrine:database:drop --force --if-exists && bin/console -e test doctrine:database:create --if-not-exists && bin/console -e test doctrine:migrations:migrate -n --allow-no-migration --all-or-nothing && rm -rf var/cache/test',
        service: 'api',
        noDeps: false,
        workDir: '/var/www/api',
        withBuilder: false,
    );

    return docker_exit_code(
        'vendor/bin/phpunit ' . implode(' ', $rawTokens),
        service: 'api',
        noDeps: false,
        workDir: '/var/www/api',
        withBuilder: false,
    );
}

#[AsTask(description: 'Runs PHPStan', aliases: ['phpstan'])]
function phpstan(
    #[AsOption(description: 'Generate baseline file', shortcut: 'b')]
    bool $baseline = false,
): int {
    if (!is_dir(variable('root_dir') . '/tools/phpstan/vendor')) {
        install();
    }

    io()->section('Running PHPStan...');

    $options = $baseline ? '--generate-baseline --allow-empty-baseline' : '';
    $command = \sprintf('/var/www/tools/phpstan/vendor/bin/phpstan analyse --memory-limit=-1 %s -v', $options);

    return docker_exit_code($command);
}

#[AsTask(description: 'Fixes Coding Style', aliases: ['cs'])]
function cs(bool $dryRun = false): int
{
    if (!is_dir(variable('root_dir') . '/tools/php-cs-fixer/vendor')) {
        install();
    }

    if ($dryRun) {
        return docker_exit_code('/var/www/tools/php-cs-fixer/vendor/bin/php-cs-fixer fix --dry-run --diff');
    }

    return docker_exit_code('/var/www/tools/php-cs-fixer/vendor/bin/php-cs-fixer fix');
}

#[AsTask(description: 'Lints AI agent documentation', aliases: ['agent-doc-lint'])]
function agent_docs(): int
{
    io()->section('Linting AI agent documentation...');

    $process = run(
        ['php', variable('root_dir') . '/tools/bin/agent-doc-lint.php'],
        context: context()->withAllowFailure(),
    );

    return $process->getExitCode() ?? 0;
}

#[AsTask(description: 'Validates Doctrine schema', aliases: ['schema-validate'])]
function schema_validate(): int
{
    io()->section('Validating Doctrine schema...');

    return docker_exit_code(
        'bin/console -e test doctrine:schema:validate --no-debug',
        service: 'api',
        noDeps: false,
        workDir: '/var/www/api',
        withBuilder: false,
    );
}
