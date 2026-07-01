<?php

declare(strict_types=1);

namespace App\Tests\Unit\Guideline;

use App\Guideline\TemplateLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Asserts the §3.2 inheritance graph against the real shipped templates, plus
 * the hard-error behaviour for missing/cyclic conforms_to.
 */
final class TemplateLoaderTest extends TestCase
{
    private function loader(): TemplateLoader
    {
        return new TemplateLoader(\dirname(__DIR__, 3) . '/config/guidelines');
    }

    #[Test]
    public function arriveHas51MetadataFieldsAndNoColumns(): void
    {
        $arrive = $this->loader()->load('arrive-v2');

        self::assertCount(51, $arrive->requiredMetadata);
        self::assertCount(0, $arrive->requiredColumns);
    }

    #[Test]
    public function prepareHas42FieldsWithCrosswalksToArrive(): void
    {
        $prepare = $this->loader()->load('prepare-v1');

        self::assertCount(42, $prepare->requiredMetadata);
        $withCrosswalk = array_filter($prepare->requiredMetadata, static fn ($m): bool => [] !== $m->crosswalk);
        self::assertGreaterThanOrEqual(26, \count($withCrosswalk));
    }

    #[Test]
    public function eqipdIsStandaloneWith18Requirements(): void
    {
        $eqipd = $this->loader()->load('eqipd-v1');

        self::assertSame([], $eqipd->conformsTo);
        self::assertCount(18, $eqipd->requiredMetadata);
        self::assertCount(0, $eqipd->requiredColumns);
    }

    #[Test]
    public function mnmsInheritsArrivesMetadataFields(): void
    {
        $mnms = $this->loader()->load('mnms-v1');

        // 20 own columns + 2 optional, and ARRIVE's 51 metadata fields inherited.
        self::assertCount(20, $mnms->requiredColumns);
        self::assertCount(2, $mnms->optionalColumns);
        self::assertCount(51, $mnms->requiredMetadata);

        $ids = array_map(static fn ($m): string => $m->id, $mnms->requiredMetadata);
        self::assertContains('humane_endpoints', $ids);
        self::assertContains('sample_size_justification', $ids);
    }

    #[Test]
    public function crosswalkTemplateIsUnionOfArriveAndPrepare(): void
    {
        $loader = $this->loader();
        $xp = $loader->load('arrive-prepare-crosswalk-v1');
        $arrive = $loader->load('arrive-v2');
        $prepare = $loader->load('prepare-v1');

        self::assertCount(
            \count($arrive->requiredMetadata) + \count($prepare->requiredMetadata),
            $xp->requiredMetadata,
        );
        // The crosswalk template owns zero fields of its own.
        self::assertSame(['arrive-v2', 'prepare-v1'], $xp->conformsTo);
    }

    #[Test]
    public function everyCrosswalkTargetResolvesToAKnownFieldId(): void
    {
        $loader = $this->loader();
        $known = [];
        foreach ($loader->loadAll() as $template) {
            foreach ($template->requiredMetadata as $meta) {
                $known[$meta->id] = true;
            }
        }

        foreach ($loader->loadAll() as $template) {
            foreach ($template->requiredMetadata as $meta) {
                foreach ($meta->crosswalk as $target) {
                    self::assertArrayHasKey($target, $known, \sprintf('Dangling crosswalk target "%s" on %s.', $target, $meta->id));
                }
            }
        }
    }

    #[Test]
    public function missingParentIsAHardError(): void
    {
        $loader = $this->makeTempLoader([
            'child.yaml' => "id: child\nname: Child\nconforms_to: [ghost]\n",
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/unknown parent "ghost"/');
        $loader->load('child');
    }

    #[Test]
    public function cyclicConformsToIsAHardError(): void
    {
        $loader = $this->makeTempLoader([
            'a.yaml' => "id: a\nname: A\nconforms_to: [b]\n",
            'b.yaml' => "id: b\nname: B\nconforms_to: [a]\n",
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cyclic conforms_to/');
        $loader->load('a');
    }

    /**
     * @param array<string, string> $files filename => yaml content
     */
    private function makeTempLoader(array $files): TemplateLoader
    {
        $dir = sys_get_temp_dir() . '/guidelines_test_' . bin2hex(random_bytes(6));
        mkdir($dir);
        foreach ($files as $name => $content) {
            file_put_contents($dir . '/' . $name, $content);
        }

        return new TemplateLoader($dir);
    }
}
