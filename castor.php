<?php

use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\guard_min_version;
use function Castor\import;
use function Castor\io;
use function Castor\notify;
use function Castor\variable;
use function docker\about;
use function docker\build;
use function docker\docker_compose_run;
use function docker\generate_certificates;
use function docker\up;
use function docker\workers_start;
use function docker\workers_stop;

guard_min_version('0.18.0');

import(__DIR__ . '/.castor');

/**
 * @return array{project_name: string, root_domain: string, extra_domains: list<string>}
 */
function create_default_variables(): array
{
    $projectName = 'metadatapp';
    $tld = 'test';
    $rootDomain = "{$projectName}.{$tld}";

    return [
        'project_name' => $projectName,
        'root_domain' => $rootDomain,
        'extra_domains' => [
            "osoma.{$rootDomain}",
            "auth.{$rootDomain}",
        ],
    ];
}

function can_send_desktop_notifications(): bool
{
    if (is_ci()) {
        return false;
    }

    if (PHP_SAPI !== 'cli' || !defined('STDOUT') || !stream_isatty(STDOUT)) {
        return false;
    }

    if (is_wsl()) {
        return command_exists('powershell.exe');
    }

    return true;
}

function is_ci(): bool
{
    return filter_var($_SERVER['CI'] ?? getenv('CI') ?: false, FILTER_VALIDATE_BOOL);
}

function is_wsl(): bool
{
    if (getenv('WSL_DISTRO_NAME') !== false || getenv('WSL_INTEROP') !== false) {
        return true;
    }

    $version = @file_get_contents('/proc/version');

    return is_string($version) && str_contains(strtolower($version), 'microsoft');
}

function command_exists(string $command): bool
{
    $result = trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null'));

    return $result !== '';
}

function notify_if_available(string $message): void
{
    if (!can_send_desktop_notifications()) {
        return;
    }

    try {
        notify($message);
    } catch (\Throwable) {
        // Notifications are optional and must not break automation.
    }
}

#[AsTask(description: 'Builds and starts the infrastructure, then install the application (composer, yarn, ...)')]
function start(): void
{
    io()->title('Starting the stack');

    // workers_stop();
    generate_certificates(force: false);
    build();
    if (is_ci()) {
        up(service: 'api', profiles: ['default']);
    } else {
        up(profiles: ['default']); // We can't start worker now, they are not installed
    }
    cache_clear();
    install();
    migrate();
    // workers_start();

    notify_if_available('The stack is now up and running.');
    io()->success('The stack is now up and running.');

    about();
}

#[AsTask(description: 'Installs the application (composer, yarn, ...)', namespace: 'app', aliases: ['install'])]
function install(): void
{
    io()->title('Installing the application');

    io()->section('Installing PHP dependencies');
    docker_compose_run('composer install -n --prefer-dist --optimize-autoloader');

    io()->section('Installing Osoma dependencies');
    if (is_ci() && is_dir(__DIR__ . '/osoma/node_modules')) {
        io()->text('Skipping Osoma install in containers (CI already installed dependencies on host).');
    } else {
        docker_compose_run('COREPACK_ENABLE_DOWNLOAD_PROMPT=0 pnpm install', service: 'osoma');
    }

    io()->section('Installing importmap');
    // docker_compose_run('bin/console importmap:install');

    qa\install();
}

#[AsTask(description: 'Clear the application cache', namespace: 'app', aliases: ['cache-clear'])]
function cache_clear(): void
{
    io()->title('Clearing the application cache');

    docker_compose_run('rm -rf var/cache/');
    // On the very first run, the vendor does not exist yet
    if (is_dir(variable('root_dir') . '/api/vendor')) {
        docker_compose_run('bin/console cache:warmup', c: context()->withAllowFailure());
    }
}

#[AsTask(description: 'Migrates database schema', namespace: 'app:db', aliases: ['migrate'])]
function migrate(): void
{
    io()->title('Migrating the database schema');

    docker_compose_run('bin/console doctrine:database:create --if-not-exists');
    docker_compose_run('bin/console doctrine:migrations:migrate -n --allow-no-migration --all-or-nothing');
}

#[AsTask(description: 'Loads fixtures', namespace: 'app:db', aliases: ['fixture'])]
function fixtures(): void
{
    io()->title('Loads fixtures');

    docker_compose_run('bin/console doctrine:fixtures:load -n --purge-with-truncate');
}
