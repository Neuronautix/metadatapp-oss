<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\JsonSchema;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use App\Mcp\JsonSchema\ParameterSchemaFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParameterSchemaFactoryTest extends TestCase
{
    #[Test]
    public function itBuildsAnInputSchemaFromMcpToolParameters(): void
    {
        $decorated = new class() implements SchemaFactoryInterface {
            public function buildSchema(string $className, string $format = 'json', string $type = Schema::TYPE_OUTPUT, ?\ApiPlatform\Metadata\Operation $operation = null, ?Schema $schema = null, ?array $serializerContext = null, bool $forceCollection = false): Schema
            {
                return new Schema(Schema::VERSION_JSON_SCHEMA);
            }
        };

        $factory = new ParameterSchemaFactory($decorated);
        $operation = new McpTool(
            name: 'list_mice',
            parameters: new Parameters([
                'species' => new QueryParameter(
                    description: 'Filter by species.',
                    schema: ['type' => 'string', 'default' => 'mouse'],
                    required: true,
                ),
                'name' => new QueryParameter(
                    description: 'Filter by subject name.',
                    schema: ['type' => 'string'],
                ),
            ]),
        );

        $schema = $factory->buildSchema('App\\Entity\\Subject', type: Schema::TYPE_INPUT, operation: $operation);

        self::assertSame('object', $schema['type'] ?? null);
        self::assertSame('string', $schema['properties']['species']['type'] ?? null);
        self::assertSame('mouse', $schema['properties']['species']['default'] ?? null);
        self::assertSame(['species'], $schema['required'] ?? null);
        self::assertSame('Filter by subject name.', $schema['properties']['name']['description'] ?? null);
    }
}
