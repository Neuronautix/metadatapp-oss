<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $confDir = $this->getProjectDir() . '/config';

        foreach (glob($confDir . '/packages/*.{php,yaml}', \GLOB_BRACE) ?: [] as $file) {
            if (!class_exists(\Symfony\AI\McpBundle\McpBundle::class) && 'mcp.yaml' === basename($file)) {
                continue;
            }

            $container->import($file);
        }

        $container->import($confDir . '/{packages}/' . $this->environment . '/*.{php,yaml}', 'glob');
        $container->import($confDir . '/services.yaml');

        if (class_exists(\Symfony\AI\McpBundle\McpBundle::class)) {
            return;
        }

        $container->extension('framework', [
            'router' => [
                'resource' => '%kernel.project_dir%/config/routes.yaml',
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $confDir = $this->getProjectDir() . '/config';

        $routes->import($confDir . '/{routes}/' . $this->environment . '/*.{php,yaml}', 'glob');
        foreach (glob($confDir . '/routes/*.{php,yaml}', \GLOB_BRACE) ?: [] as $file) {
            if (!class_exists(\Symfony\AI\McpBundle\McpBundle::class) && 'mcp.yaml' === basename($file)) {
                continue;
            }

            $routes->import($file);
        }

        $routes->import($confDir . '/routes.yaml');
    }
}
