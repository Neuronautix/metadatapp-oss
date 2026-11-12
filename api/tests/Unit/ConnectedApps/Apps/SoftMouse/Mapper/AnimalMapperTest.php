<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\SoftMouse\Mapper;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\AnimalDto;
use App\ConnectedApps\Apps\SoftMouse\Mapper\AnimalMapper;
use App\Entity\Cage;
use App\Entity\Strain;
use App\Entity\Subject;
use App\Enum\HealthStatus;
use App\Enum\Sex;
use App\Enum\Species;
use App\Repository\CageRepository;
use App\Repository\StrainRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AnimalMapperTest extends TestCase
{
    private AnimalMapper $mapper;
    private CageRepository&MockObject $cageRepository;
    private StrainRepository&MockObject $strainRepository;

    protected function setUp(): void
    {
        $this->cageRepository = $this->createMock(CageRepository::class);
        $this->strainRepository = $this->createMock(StrainRepository::class);
        $this->mapper = new AnimalMapper($this->cageRepository, $this->strainRepository);
    }

    #[Test]
    public function mapFromDtoSetsSoftmouseExternalId(): void
    {
        $dto = $this->createAnimalDto(guiSid: 12345, physicalTag: 'TAG001');
        $subject = new Subject();
        $this->strainRepository->method('findOneBy')->willReturn(new Strain());
        $this->cageRepository->method('findOneBy')->willReturn(new Cage());

        $this->mapper->mapFromDto($dto, $subject);

        $this->assertSame('12345', $subject->getSoftmouseExternalId());
    }

    #[Test]
    public function mapFromDtoSetsNameWithSmPrefix(): void
    {
        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'ABC123');
        $subject = new Subject();
        $this->strainRepository->method('findOneBy')->willReturn(new Strain());
        $this->cageRepository->method('findOneBy')->willReturn(new Cage());

        $this->mapper->mapFromDto($dto, $subject);

        $this->assertSame('SM-ABC123', $subject->getName());
    }

    #[Test]
    public function mapFromDtoSetsSpeciesToMouse(): void
    {
        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'TEST');
        $subject = new Subject();
        $this->strainRepository->method('findOneBy')->willReturn(new Strain());
        $this->cageRepository->method('findOneBy')->willReturn(new Cage());

        $this->mapper->mapFromDto($dto, $subject);

        $this->assertSame(Species::Mouse, $subject->getSpecies());
    }

    #[Test]
    public function mapFromDtoSetsBirthDate(): void
    {
        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'TEST', birthDate: '2024-06-15');
        $subject = new Subject();
        $this->strainRepository->method('findOneBy')->willReturn(new Strain());
        $this->cageRepository->method('findOneBy')->willReturn(new Cage());

        $this->mapper->mapFromDto($dto, $subject);

        $this->assertSame('2024-06-15', $subject->getBirthAt()->format('Y-m-d'));
    }

    #[Test]
    public function mapFromDtoSetsGenotype(): void
    {
        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'TEST', genotype: 'WT/WT');
        $subject = new Subject();
        $this->strainRepository->method('findOneBy')->willReturn(new Strain());
        $this->cageRepository->method('findOneBy')->willReturn(new Cage());

        $this->mapper->mapFromDto($dto, $subject);

        $this->assertSame('WT/WT', $subject->getGenotype());
    }

    #[Test]
    public function mapFromDtoSetsHealthStatusToNormal(): void
    {
        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'TEST');
        $subject = new Subject();
        $this->strainRepository->method('findOneBy')->willReturn(new Strain());
        $this->cageRepository->method('findOneBy')->willReturn(new Cage());

        $this->mapper->mapFromDto($dto, $subject);

        $this->assertSame(HealthStatus::NORMAL, $subject->getHealthStatus());
    }

    #[Test]
    #[DataProvider('sexMappingProvider')]
    public function mapFromDtoMapsSexCorrectly(?int $dtoSex, Sex $expectedSex): void
    {
        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'TEST', sex: $dtoSex);
        $subject = new Subject();
        $this->strainRepository->method('findOneBy')->willReturn(new Strain());
        $this->cageRepository->method('findOneBy')->willReturn(new Cage());

        $this->mapper->mapFromDto($dto, $subject);

        $this->assertSame($expectedSex, $subject->getSex());
    }

    /**
     * @return iterable<string, array{int|null, Sex}>
     */
    public static function sexMappingProvider(): iterable
    {
        yield 'sex 0 maps to Female' => [0, Sex::Female];
        yield 'sex 1 maps to Male' => [1, Sex::Male];
        yield 'sex 2 maps to Unknown' => [2, Sex::Unknown];
        yield 'null sex maps to Unknown' => [null, Sex::Unknown];
    }

    #[Test]
    public function mapFromDtoUsesExistingStrain(): void
    {
        $existingStrain = new Strain();
        $existingStrain->setName('C57BL/6');
        $this->strainRepository->method('findOneBy')
            ->with(['name' => 'C57BL/6'])
            ->willReturn($existingStrain)
        ;
        $this->cageRepository->method('findOneBy')->willReturn(new Cage());

        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'TEST', strain: 'C57BL/6');
        $subject = new Subject();

        $this->mapper->mapFromDto($dto, $subject);

        $this->assertSame($existingStrain, $subject->getStrain());
    }

    #[Test]
    public function mapFromDtoCreatesNewStrainWhenNotFoundAndForceCreateIsTrue(): void
    {
        $this->strainRepository->method('findOneBy')->willReturn(null);
        $this->cageRepository->method('findOneBy')->willReturn(new Cage());

        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'TEST', strain: 'NewStrain');
        $subject = new Subject();

        $this->mapper->mapFromDto($dto, $subject, forceCreateStrain: true);

        $this->assertNotNull($subject->getStrain());
        $this->assertSame('NewStrain', $subject->getStrain()->getName());
    }

    #[Test]
    public function mapFromDtoThrowsExceptionWhenStrainNotFoundAndForceCreateIsFalse(): void
    {
        $this->strainRepository->method('findOneBy')->willReturn(null);

        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'TEST', strain: 'MissingStrain');
        $subject = new Subject();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Strain not found: MissingStrain');

        $this->mapper->mapFromDto($dto, $subject, forceCreateStrain: false);
    }

    #[Test]
    public function mapFromDtoUsesExistingCage(): void
    {
        $existingCage = new Cage();
        $existingCage->setSoftmouseExternalId('CAGE-001');
        $this->strainRepository->method('findOneBy')->willReturn(new Strain());
        $this->cageRepository->method('findOneBy')
            ->with(['softmouseExternalId' => 'CAGE-001'])
            ->willReturn($existingCage)
        ;

        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'TEST', cageSid: 'CAGE-001');
        $subject = new Subject();

        $this->mapper->mapFromDto($dto, $subject);

        $this->assertSame($existingCage, $subject->getCage());
    }

    #[Test]
    public function mapFromDtoCreatesNewCageWhenNotFoundAndForceCreateIsTrue(): void
    {
        $this->strainRepository->method('findOneBy')->willReturn(new Strain());
        $this->cageRepository->method('findOneBy')->willReturn(null);

        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'TEST', cageSid: 'NEW-CAGE');
        $subject = new Subject();

        $this->mapper->mapFromDto($dto, $subject, forceCreateCage: true);

        $this->assertNotNull($subject->getCage());
        $this->assertSame('NEW-CAGE', $subject->getCage()->getSoftmouseExternalId());
    }

    #[Test]
    public function mapFromDtoSetsWeightWithinExpectedRange(): void
    {
        $dto = $this->createAnimalDto(guiSid: 1, physicalTag: 'TEST');
        $subject = new Subject();
        $this->strainRepository->method('findOneBy')->willReturn(new Strain());
        $this->cageRepository->method('findOneBy')->willReturn(new Cage());

        $this->mapper->mapFromDto($dto, $subject);

        $this->assertGreaterThanOrEqual(20.0, $subject->getWeight());
        $this->assertLessThanOrEqual(30.0, $subject->getWeight());
    }

    private function createAnimalDto(
        int $guiSid,
        string $physicalTag,
        string $birthDate = '2024-01-01',
        string $strain = 'TestStrain',
        ?int $sex = null,
        ?string $genotype = null,
        ?string $cageSid = null,
    ): AnimalDto {
        return new AnimalDto(
            guiSid: $guiSid,
            altId: null,
            birthDate: $birthDate,
            createdDateTime: '2024-01-01T00:00:00Z',
            strain: $strain,
            cageSid: $cageSid,
            sex: $sex,
            physicalTag: $physicalTag,
            genotype: $genotype,
        );
    }
}
