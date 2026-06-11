<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\LookupController;
use App\Entity\Strain;
use App\Lookup\ExternalLookupService;
use App\Lookup\StrainLookupResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class LookupControllerTest extends TestCase
{
    #[Test]
    public function searchReturnsMappedItems(): void
    {
        $lookupService = $this->createMock(ExternalLookupService::class);
        $lookupService
            ->expects($this->once())
            ->method('search')
            ->with('orcid', 'Ada')
            ->willReturn([[
                'label' => 'Ada Lovelace',
                'sublabel' => 'Analytical Engine Institute',
                'value' => '0000-0001-2345-6789',
                'externalId' => 'https://orcid.org/0000-0001-2345-6789',
                'scheme' => 'ORCID',
            ]])
        ;

        $controller = new LookupController($lookupService, $this->createMock(StrainLookupResolver::class));
        $response = $controller->search('orcid', new Request(query: ['q' => 'Ada']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'items' => [[
                'label' => 'Ada Lovelace',
                'sublabel' => 'Analytical Engine Institute',
                'value' => '0000-0001-2345-6789',
                'externalId' => 'https://orcid.org/0000-0001-2345-6789',
                'scheme' => 'ORCID',
            ]],
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function searchReturnsValidationErrorsForUnsupportedSources(): void
    {
        $lookupService = $this->createMock(ExternalLookupService::class);
        $lookupService
            ->expects($this->once())
            ->method('search')
            ->with('unsupported', 'Ada')
            ->willThrowException(new \InvalidArgumentException('Unsupported lookup source "unsupported".'))
        ;

        $controller = new LookupController($lookupService, $this->createMock(StrainLookupResolver::class));
        $response = $controller->search('unsupported', new Request(query: ['q' => 'Ada']));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Unsupported lookup source "unsupported".',
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function resolveStrainReturnsTheLocalIri(): void
    {
        $strain = new Strain();
        $strain->setId(Uuid::fromString('b769ca5f-86dd-4e8b-bbd2-7f698a4a47ef'));
        $strain
            ->setName('C57BL/6J')
            ->setLink('http://www.ebi.ac.uk/efo/EFO_0004472')
        ;

        $resolver = $this->createMock(StrainLookupResolver::class);
        $resolver
            ->expects($this->once())
            ->method('resolve')
            ->with('C57BL/6J', 'http://www.ebi.ac.uk/efo/EFO_0004472')
            ->willReturn($strain)
        ;

        $controller = new LookupController($this->createMock(ExternalLookupService::class), $resolver);
        $response = $controller->resolveStrain(new Request(content: json_encode([
            'label' => 'C57BL/6J',
            'externalId' => 'http://www.ebi.ac.uk/efo/EFO_0004472',
        ], \JSON_THROW_ON_ERROR)));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'value' => '/strains/b769ca5f-86dd-4e8b-bbd2-7f698a4a47ef',
            'label' => 'C57BL/6J',
            'externalId' => 'http://www.ebi.ac.uk/efo/EFO_0004472',
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function resolveStrainRejectsInvalidPayloads(): void
    {
        $controller = new LookupController(
            $this->createMock(ExternalLookupService::class),
            $this->createMock(StrainLookupResolver::class),
        );

        $response = $controller->resolveStrain(new Request(content: json_encode([], \JSON_THROW_ON_ERROR)));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Invalid strain resolution payload.',
            'detail' => 'The strain label is required.',
        ], json_decode((string) $response->getContent(), true));
    }
}
