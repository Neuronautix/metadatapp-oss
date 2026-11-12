<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Elabftw;

use App\ConnectedApps\Apps\Elabftw\ElabftwService;
use App\ConnectedApps\Apps\Elabftw\Synchronizer\ExperimentSynchronizer;
use App\ConnectedApps\Apps\Elabftw\Synchronizer\SubjectSynchronizer;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Entity\User;
use App\Enum\AppCode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ElabftwServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ExperimentSynchronizer $experimentSynchronizer;
    private SubjectSynchronizer $subjectSynchronizer;
    private LoggerInterface $logger;
    private ElabftwService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->experimentSynchronizer = $this->createMock(ExperimentSynchronizer::class);
        $this->subjectSynchronizer = $this->createMock(SubjectSynchronizer::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ElabftwService(
            $this->em,
            $this->experimentSynchronizer,
            $this->subjectSynchronizer,
            $this->logger
        );
    }

    #[Test]
    public function supportsCode(): void
    {
        $this->assertTrue($this->service->supportsCode(AppCode::Elabftw));
        $this->assertFalse($this->service->supportsCode(AppCode::SoftMouse));
    }
    //
    //    #[Test]
    //    public function supportsEntitySyncTo(): void
    //    {
    //        $this->assertTrue($this->service->supportsEntitySyncTo(Experiment::class));
    //        $this->assertFalse($this->service->supportsEntitySyncTo(User::class));
    //    }
    //
    //    #[Test]
    //    public function supportsEntitySyncFrom(): void
    //    {
    //        $this->assertFalse($this->service->supportsEntitySyncFrom(Experiment::class));
    //    }
    //
    //    #[Test]
    //    public function syncOneToExternalExperiment(): void
    //    {
    //        $connectedApp = $this->createMock(ConnectedApp::class);
    //        $uuid = Uuid::v4();
    //
    //        $this->experimentSynchronizer->expects($this->once())
    //            ->method('syncOneTo')
    //            ->with($uuid)
    //        ;
    //
    //        $this->em->expects($this->once())->method('flush');
    //
    //        $this->service->syncOneToExternal($connectedApp, Experiment::class, $uuid);
    //    }
    //
    //    #[Test]
    //    public function syncOneToExternalThrowsExceptionForUnsupportedEntity(): void
    //    {
    //        $connectedApp = $this->createMock(ConnectedApp::class);
    //        $uuid = Uuid::v4();
    //
    //        $this->expectException(\Exception::class);
    //        $this->expectExceptionMessage('Entity classname "App\Entity\User" not implemented');
    //
    //        $this->service->syncOneToExternal($connectedApp, User::class, $uuid);
    //    }
    //
    //    #[Test]
    //    public function syncAllToExternalExperiment(): void
    //    {
    //        $connectedApp = $this->createMock(ConnectedApp::class);
    //
    //        $this->experimentSynchronizer->expects($this->once())
    //            ->method('syncAllTo')
    //            ->with($connectedApp)
    //        ;
    //
    //        $this->service->syncAllToExternal($connectedApp, Experiment::class);
    //    }
    //
    //    #[Test]
    //    public function syncAllToExternalThrowsExceptionForUnsupportedEntity(): void
    //    {
    //        $connectedApp = $this->createMock(ConnectedApp::class);
    //
    //        $this->expectException(\Exception::class);
    //        $this->expectExceptionMessage('Entity classname "App\Entity\User" not implemented');
    //
    //        $this->service->syncAllToExternal($connectedApp, User::class);
    //    }
}
