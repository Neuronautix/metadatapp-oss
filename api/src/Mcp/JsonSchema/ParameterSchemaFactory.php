<?php

declare(strict_types=1);

namespace App\Mcp\JsonSchema;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation;

final readonly class ParameterSchemaFactory implements SchemaFactoryInterface
{
    public function __construct(
        private SchemaFactoryInterface $decorated,
    ) {
    }

    public function buildSchema(string $className, string $format = 'json', string $type = Schema::TYPE_OUTPUT, ?Operation $operation = null, ?Schema $schema = null, ?array $serializerContext = null, bool $forceCollection = false): Schema
    {
        if (Schema::TYPE_INPUT !== $type || !$operation instanceof McpTool) {
            return $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);
        }

        $parameterSchema = new Schema(Schema::VERSION_JSON_SCHEMA);
        unset($parameterSchema['$schema']);
        $parameterSchema['type'] = 'object';
        $parameterSchema['properties'] = [];

        $required = [];
        foreach ($operation->getParameters() ?? [] as $key => $parameter) {
            $propertySchema = $parameter->getSchema() ?? ['type' => 'string'];
            if ($description = $parameter->getDescription()) {
                $propertySchema['description'] = $description;
            }

            $parameterSchema['properties'][$key] = $propertySchema;

            if (true === $parameter->getRequired()) {
                $required[] = $key;
            }
        }

        if ([] !== $required) {
            $parameterSchema['required'] = $required;
        }

        return $parameterSchema;
    }
}
