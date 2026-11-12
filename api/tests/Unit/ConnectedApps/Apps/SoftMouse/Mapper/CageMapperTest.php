<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\SoftMouse\Mapper;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\CageDto;
use App\ConnectedApps\Apps\SoftMouse\Mapper\CageMapper;
use App\Entity\Cage;
use App\Enum\BeddingType;
use App\Enum\CageFormat;
use App\Enum\CageType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CageMapperTest extends TestCase
{
    private CageMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new CageMapper();
    }

    #[Test]
    public function mapFromExternalSetsSoftmouseExternalId(): void
    {
        $dto = $this->createCageDto(cageSid: 12345, cageTag: 'CAGE-001', cageType: '5 max');
        $cage = new Cage();

        $this->mapper->mapFromExternal($dto, $cage);

        $this->assertSame('12345', $cage->getSoftmouseExternalId());
    }

    #[Test]
    public function mapFromExternalSetsStandardCageType(): void
    {
        $dto = $this->createCageDto(cageSid: 1, cageTag: 'TEST', cageType: '5 max');
        $cage = new Cage();

        $this->mapper->mapFromExternal($dto, $cage);

        $this->assertSame(CageType::Standard, $cage->getType());
    }

    #[Test]
    public function mapFromExternalSetsOakBeddingType(): void
    {
        $dto = $this->createCageDto(cageSid: 1, cageTag: 'TEST', cageType: '5 max');
        $cage = new Cage();

        $this->mapper->mapFromExternal($dto, $cage);

        $this->assertSame(BeddingType::Oak, $cage->getBeddingType());
    }

    #[Test]
    #[DataProvider('cageFormatMappingProvider')]
    public function mapFromExternalMapsCageFormat(string $dtoCageType, CageFormat $expectedFormat): void
    {
        $dto = $this->createCageDto(cageSid: 1, cageTag: 'TEST', cageType: $dtoCageType);
        $cage = new Cage();

        $this->mapper->mapFromExternal($dto, $cage);

        $this->assertSame($expectedFormat, $cage->getFormat());
    }

    /**
     * @return iterable<string, array{string, CageFormat}>
     */
    public static function cageFormatMappingProvider(): iterable
    {
        yield '5 max maps to T1' => ['5 max', CageFormat::T1];
        yield 'plus maps to T2' => ['plus', CageFormat::T2];
        yield 'moins maps to T3' => ['moins', CageFormat::T3];
        yield 'trop maps to T4' => ['trop', CageFormat::T4];
        yield 'unknown type defaults to T1' => ['unknown', CageFormat::T1];
        yield 'empty string defaults to T1' => ['', CageFormat::T1];
    }

    private function createCageDto(int $cageSid, string $cageTag, string $cageType): CageDto
    {
        return new CageDto(
            id: 1,
            ownerUser: 'testuser',
            createdDateTime: new \DateTime(),
            lastUpdatedDateTime: new \DateTime(),
            creatorUser: 'creator',
            cageSid: $cageSid,
            state: 1,
            cageTag: $cageTag,
            cageType: $cageType,
        );
    }
}
