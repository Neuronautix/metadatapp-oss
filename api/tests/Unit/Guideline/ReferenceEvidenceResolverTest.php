<?php

declare(strict_types=1);

namespace App\Tests\Unit\Guideline;

use App\ConnectedApps\Apps\GuidelinesHub\GuidelinesHubDataProvider;
use App\Guideline\Evidence\EvidenceBundle;
use App\Guideline\Evidence\EvidenceItem;
use App\Guideline\Evidence\ReferenceEvidenceResolver;
use App\Guideline\Schema\RequiredMetadata;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The deterministic `reference` citation source: a guideline field is mapped onto
 * the canonical standard text + official URL from the bundled GuidelinesHub
 * catalogue, restricted to the field's own standard, and surfaced ONLY via
 * forField() — never as a one-click suggestion.
 */
final class ReferenceEvidenceResolverTest extends TestCase
{
    private function resolver(): ReferenceEvidenceResolver
    {
        // The provider is static/bundled — no external calls, no container needed.
        return new ReferenceEvidenceResolver(new GuidelinesHubDataProvider());
    }

    private function field(
        string $id,
        ?string $arrive = null,
        ?string $prepare = null,
        ?string $eqipd = null,
    ): RequiredMetadata {
        return new RequiredMetadata(
            id: $id,
            arriveSection: $arrive,
            prepareSection: $prepare,
            eqipdSection: $eqipd,
        );
    }

    #[Test]
    public function anArriveFieldCitesTheArriveStandardUrlAndCanonicalText(): void
    {
        $items = $this->resolver()->forField($this->field('species', arrive: 'Experimental animals / Species'));

        self::assertCount(1, $items);
        $ref = $items[0];
        self::assertSame(EvidenceItem::SOURCE_REFERENCE, $ref->source);
        self::assertSame('species', $ref->fieldId);
        self::assertNotNull($ref->url);
        self::assertStringContainsString('arriveguidelines.org', $ref->url);
        self::assertStringContainsString('ARRIVE 2.0', $ref->label);
        // Canonical ARRIVE "Experimental animals" text.
        self::assertStringContainsString('species-appropriate details', $ref->value);
        self::assertNotNull($ref->confidence);
        self::assertGreaterThan(0.0, $ref->confidence);
        // The url is serialised in the full evidence shape.
        self::assertSame($ref->url, $ref->toArray()['url']);
    }

    #[Test]
    public function anArriveFieldWithoutAStrongMatchStillLinksTheArriveStandard(): void
    {
        // "humane_endpoints" has no exact catalogue title; it must still degrade
        // to an arriveguidelines.org citation rather than throw or return nothing.
        $items = $this->resolver()->forField($this->field('humane_endpoints', arrive: 'Animal care and monitoring / Humane endpoints'));

        self::assertCount(1, $items);
        self::assertSame(EvidenceItem::SOURCE_REFERENCE, $items[0]->source);
        self::assertNotNull($items[0]->url);
        self::assertStringContainsString('arriveguidelines.org', (string) $items[0]->url);
    }

    #[Test]
    public function aPrepareFieldMapsToThePrepareStandardUrl(): void
    {
        // prepare_* id prefix wins even though the field also declares an arrive_section.
        $items = $this->resolver()->forField($this->field(
            'prepare_humane_endpoints',
            arrive: 'Animal care and monitoring / Humane endpoints',
            prepare: '3. Ethical issues, harm-benefit assessment and humane endpoints',
        ));

        self::assertCount(1, $items);
        self::assertNotNull($items[0]->url);
        self::assertStringContainsString('nc3rs.org.uk', (string) $items[0]->url);
        self::assertStringContainsString('PREPARE', $items[0]->label);
    }

    #[Test]
    public function anEqipdFieldMapsToTheEqipdStandardUrl(): void
    {
        $items = $this->resolver()->forField($this->field('eqipd_data_documentation', eqipd: 'Data integrity'));

        self::assertCount(1, $items);
        self::assertNotNull($items[0]->url);
        self::assertStringContainsString('eqipd.eu', (string) $items[0]->url);
        self::assertStringContainsString('EQIPD', $items[0]->label);
    }

    #[Test]
    public function aWeakMatchDegradesToALowConfidenceStandardUrlWithoutThrowing(): void
    {
        // A field whose tokens overlap nothing in the catalogue still yields at
        // most a low-confidence standard-url citation — never an exception.
        $items = $this->resolver()->forField($this->field('eqipd_process_owner', eqipd: 'Research team'));

        // Either a (weak) best match or the canonical standard url — but always
        // exactly one reference item linking the published standard.
        self::assertCount(1, $items);
        self::assertNotNull($items[0]->url);
        self::assertStringContainsString('eqipd.eu', (string) $items[0]->url);
    }

    #[Test]
    public function referenceItemsAreInForFieldButNeverInSuggestions(): void
    {
        $field = $this->field('species', arrive: 'Experimental animals / Species');

        $bundle = new EvidenceBundle(
            [new EvidenceItem('species', EvidenceItem::SOURCE_COMPUTED, 'Species', 'mouse', 'computed from 3 subjects', 1.0)],
            $this->resolver(),
        );

        $forField = $bundle->forField($field);
        $hasReference = array_filter($forField, static fn (EvidenceItem $i): bool => EvidenceItem::SOURCE_REFERENCE === $i->source);
        self::assertNotEmpty($hasReference, 'reference citation reaches forField (and thus the LLM grounding payload + evidence JSON)');

        $suggestions = $bundle->suggestionsForField($field);
        foreach ($suggestions as $s) {
            self::assertNotSame(EvidenceItem::SOURCE_REFERENCE, $s->source, 'a citation/definition is never a one-click fill value');
        }
        // The computed value is still offered as a suggestion.
        self::assertNotEmpty($suggestions);
    }

    #[Test]
    public function aBundleWithoutAResolverIsUnchanged(): void
    {
        $field = $this->field('species', arrive: 'Experimental animals / Species');
        $bundle = new EvidenceBundle([
            new EvidenceItem('species', EvidenceItem::SOURCE_COMPUTED, 'Species', 'mouse', 'computed', 1.0),
        ]);

        foreach ($bundle->forField($field) as $i) {
            self::assertNotSame(EvidenceItem::SOURCE_REFERENCE, $i->source);
        }
    }
}
