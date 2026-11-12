<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Runtime;

use App\AI\Mcp\ToolAccessPolicy;
use App\AI\Runtime\AgenticToolCatalog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AgenticToolCatalogTest extends TestCase
{
    #[Test]
    public function itReturnsToolDefinitionsForRegisteredToolsOnly(): void
    {
        $defs = (new AgenticToolCatalog())->definitions([
            new ToolAccessPolicy('list_my_references', true, false, false),
            new ToolAccessPolicy('standardize_references', true, false, false),
            new ToolAccessPolicy('no_such_tool', true, false, false),
        ]);

        $names = array_column($defs, 'name');
        self::assertContains('list_my_references', $names);
        self::assertContains('standardize_references', $names);
        self::assertNotContains('no_such_tool', $names, 'Tools without a schema are skipped.');

        foreach ($defs as $def) {
            self::assertSame('object', $def['input_schema']['type']);
            self::assertNotSame('', $def['description']);
        }
    }

    #[Test]
    public function theStandardizeToolRequiresReferenceIds(): void
    {
        $defs = (new AgenticToolCatalog())->definitions([new ToolAccessPolicy('standardize_references', true, false, false)]);

        self::assertCount(1, $defs);
        self::assertContains('referenceIds', $defs[0]['input_schema']['required']);
    }
}
