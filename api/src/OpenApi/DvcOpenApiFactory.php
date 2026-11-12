<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Components;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\Yaml\Yaml;

final class DvcOpenApiFactory implements OpenApiFactoryInterface
{
    /**
     * Path prefixes whose operations are stripped from the public spec when
     * $hideConnectedAppsFromDocs is true. Controls what appears in Swagger UI.
     */
    private const CONNECTED_APP_PATH_PREFIXES = [
        '/connected_apps',
        '/dvc/',
    ];

    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
        private readonly string $projectDir,
        private readonly bool $hideConnectedAppsFromDocs = false,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        if ($this->hideConnectedAppsFromDocs) {
            $openApi = $this->stripConnectedAppPaths($openApi);
        }

        $yamlPath = $this->projectDir . '/src/ConnectedApps/Apps/Tecniplast/docs/DvcProxyApi.openapi.yaml';
        if (!file_exists($yamlPath) || $this->hideConnectedAppsFromDocs) {
            return $openApi;
        }

        $dvcSpec = Yaml::parseFile($yamlPath);

        $components = $openApi->getComponents();

        // Merge schemas
        if (isset($dvcSpec['components']['schemas'])) {
            $schemas = $components->getSchemas() ?? new \ArrayObject();
            foreach ($dvcSpec['components']['schemas'] as $name => $schema) {
                $schemas[$name] = new \ArrayObject($schema);
            }
            $components = $components->withSchemas($schemas);
        }

        // Merge parameters
        if (isset($dvcSpec['components']['parameters'])) {
            $parameters = $components->getParameters() ?? new \ArrayObject();
            foreach ($dvcSpec['components']['parameters'] as $name => $parameter) {
                $parameters[$name] = new \ArrayObject($parameter);
            }
            $components = $components->withParameters($parameters);
        }

        // Merge responses
        if (isset($dvcSpec['components']['responses'])) {
            $responses = $components->getResponses() ?? new \ArrayObject();
            foreach ($dvcSpec['components']['responses'] as $name => $response) {
                $responses[$name] = new \ArrayObject($response);
            }
            $components = $components->withResponses($responses);
        }

        $openApi = $openApi->withComponents($components);

        // Merge paths
        if (isset($dvcSpec['paths'])) {
            $paths = $openApi->getPaths();
            foreach ($dvcSpec['paths'] as $path => $pathItemData) {
                $pathItem = $paths->getPath($path) ?? new PathItem();

                foreach ($pathItemData as $method => $operationData) {
                    $methodName = strtolower((string) $method);

                    // Get existing operation if available to preserve security and other metadata
                    $existingOperation = match ($methodName) {
                        'get' => $pathItem->getGet(),
                        'post' => $pathItem->getPost(),
                        'put' => $pathItem->getPut(),
                        'delete' => $pathItem->getDelete(),
                        'patch' => $pathItem->getPatch(),
                        default => null,
                    };

                    $operation = new Operation(
                        operationId: $operationData['operationId'] ?? $existingOperation?->getOperationId(),
                        tags: $operationData['tags'] ?? ($existingOperation?->getTags() ?? ['Tecniplast']),
                        responses: $this->buildResponses($operationData['responses'] ?? []),
                        summary: $operationData['summary'] ?? ($existingOperation?->getSummary() ?? ''),
                        description: $operationData['description'] ?? ($existingOperation?->getDescription() ?? ''),
                        parameters: $this->buildParameters($operationData['parameters'] ?? [], $components),
                        requestBody: isset($operationData['requestBody'])
                            ? $this->buildRequestBody($operationData['requestBody'])
                            : ($existingOperation?->getRequestBody()),
                        security: $existingOperation?->getSecurity() ?? [['oauth' => []]]
                    );

                    $pathItem = match ($methodName) {
                        'get' => $pathItem->withGet($operation),
                        'post' => $pathItem->withPost($operation),
                        'put' => $pathItem->withPut($operation),
                        'delete' => $pathItem->withDelete($operation),
                        'patch' => $pathItem->withPatch($operation),
                        default => $pathItem,
                    };
                }
                $paths->addPath((string) $path, $pathItem);
            }
            $openApi = $openApi->withPaths($paths);
        }

        return $openApi;
    }

    private function stripConnectedAppPaths(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();
        foreach (array_keys($paths->getPaths()) as $path) {
            foreach (self::CONNECTED_APP_PATH_PREFIXES as $prefix) {
                if (str_starts_with((string) $path, $prefix)) {
                    $paths->addPath((string) $path, new PathItem());
                    break;
                }
            }
        }

        return $openApi->withPaths($paths);
    }

    /**
     * @param array<string|int, mixed> $responsesData
     *
     * @return array<string, Response>
     */
    private function buildResponses(array $responsesData): array
    {
        $responses = [];
        foreach ($responsesData as $statusCode => $data) {
            $responses[(string) $statusCode] = new Response(
                description: (string) ($data['description'] ?? ''),
                content: isset($data['content']) ? new \ArrayObject($data['content']) : null
            );
        }

        return $responses;
    }

    /**
     * @param array<int, mixed> $parametersData
     *
     * @return Parameter[]
     */
    private function buildParameters(array $parametersData, Components $components): array
    {
        $parameters = [];
        $registry = $components->getParameters() ?? new \ArrayObject();

        foreach ($parametersData as $data) {
            if (isset($data['$ref'])) {
                $refName = basename($data['$ref']);
                if (isset($registry[$refName])) {
                    $paramData = (array) $registry[$refName];
                    $parameters[] = new Parameter(
                        name: (string) $paramData['name'],
                        in: (string) $paramData['in'],
                        description: (string) ($paramData['description'] ?? ''),
                        required: (bool) ($paramData['required'] ?? false),
                        schema: isset($paramData['schema']) ? (array) $paramData['schema'] : []
                    );
                }
            } else {
                $parameters[] = new Parameter(
                    name: (string) $data['name'],
                    in: (string) $data['in'],
                    description: (string) ($data['description'] ?? ''),
                    required: (bool) ($data['required'] ?? false),
                    schema: isset($data['schema']) ? (array) $data['schema'] : []
                );
            }
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $requestBodyData
     */
    private function buildRequestBody(array $requestBodyData): RequestBody
    {
        return new RequestBody(
            description: (string) ($requestBodyData['description'] ?? ''),
            content: new \ArrayObject($requestBodyData['content'] ?? []),
            required: (bool) ($requestBodyData['required'] ?? false)
        );
    }
}
