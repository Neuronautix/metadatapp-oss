<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Elabftw\Mapper;

use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentCreateRequestDto;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentUpdateRequestDto;
use App\ConnectedApps\Apps\Elabftw\Mapper\ExperimentMapper;
use App\ConnectedApps\Error\SyncValidityException;
use App\Entity\Experiment;
use App\Enum\ExperimentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ExperimentMapperTest extends TestCase
{
    private ExperimentMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ExperimentMapper();
    }

    #[Test]
    public function mapToExternalReturnsCreateDtoWhenNoExternalId(): void
    {
        $experiment = $this->createValidExperiment();

        $result = $this->mapper->mapToExternal($experiment);

        $this->assertInstanceOf(ExperimentCreateRequestDto::class, $result);
    }

    #[Test]
    public function mapToExternalReturnsUpdateDtoWhenHasExternalId(): void
    {
        $experiment = $this->createValidExperiment();
        $experiment->setElaftwExternalId('elabftw-123');

        $result = $this->mapper->mapToExternal($experiment);

        $this->assertInstanceOf(ExperimentUpdateRequestDto::class, $result);
    }

    #[Test]
    public function mapToExternalThrowsExceptionWhenTitleIsMissing(): void
    {
        $experiment = new Experiment();
        // Initialize name to empty string to avoid uninitialized property error
        $experiment->setName('');
        $experiment->setDescription('Description');
        $experiment->setGoal('Goal');
        $experiment->setType(ExperimentType::TypeA);

        $this->expectException(SyncValidityException::class);
        $this->expectExceptionMessage('Missing required experiment fields for eLabFTW sync.');

        $this->mapper->mapToExternal($experiment);
    }

    #[Test]
    public function mapToExternalThrowsExceptionWhenDescriptionIsMissing(): void
    {
        $experiment = new Experiment();
        $experiment->setName('Test Experiment');
        $experiment->setGoal('Goal');
        $experiment->setType(ExperimentType::TypeA);

        $this->expectException(SyncValidityException::class);
        $this->expectExceptionMessage('Missing required experiment fields for eLabFTW sync.');

        $this->mapper->mapToExternal($experiment);
    }

    #[Test]
    public function mapToExternalAllowsMissingGoal(): void
    {
        $experiment = new Experiment();
        $experiment->setName('Test Experiment');
        $experiment->setDescription('Description');
        $experiment->setType(ExperimentType::TypeA);

        $result = $this->mapper->mapToExternal($experiment);

        $this->assertInstanceOf(ExperimentCreateRequestDto::class, $result);
        $this->assertSame([], $result->tags);
    }

    #[Test]
    public function mapToExternalThrowsExceptionForInvalidEntityType(): void
    {
        $invalidEntity = $this->createMock(\App\Entity\ConnectedAppEntityInterface::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected Experiment entity.');

        $this->mapper->mapToExternal($invalidEntity);
    }

    #[Test]
    #[DataProvider('experimentTypeStatusMappingProvider')]
    public function mapToExternalMapsExperimentTypeToStatus(ExperimentType $type, int $expectedStatus): void
    {
        $experiment = $this->createValidExperiment();
        $experiment->setType($type);

        $result = $this->mapper->mapToExternal($experiment);

        // Access status through reflection since DTO may have private properties
        $reflection = new \ReflectionClass($result);
        $statusProperty = $reflection->getProperty('status');
        $statusProperty->setAccessible(true);
        $this->assertSame($expectedStatus, $statusProperty->getValue($result));
    }

    /**
     * @return iterable<string, array{ExperimentType, int}>
     */
    public static function experimentTypeStatusMappingProvider(): iterable
    {
        yield 'TypeA maps to status 1' => [ExperimentType::TypeA, 1];
        yield 'TypeB maps to status 2' => [ExperimentType::TypeB, 2];
        yield 'TypeC maps to status 3' => [ExperimentType::TypeC, 3];
    }

    private function createValidExperiment(): Experiment
    {
        $experiment = new Experiment();
        $experiment->setName('Test Experiment');
        $experiment->setDescription('Test Description');
        $experiment->setGoal('Test Goal');
        $experiment->setType(ExperimentType::TypeA);

        return $experiment;
    }
}
