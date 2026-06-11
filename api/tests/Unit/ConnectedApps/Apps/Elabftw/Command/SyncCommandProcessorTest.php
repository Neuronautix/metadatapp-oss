<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Elabftw\Command;

use App\ConnectedApps\Apps\Elabftw\Command\SyncCommandProcessor;
use App\ConnectedApps\Apps\Elabftw\ElabftwService;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Enum\AppCode;
use App\Repository\ConnectedAppRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SyncCommandProcessorTest extends TestCase
{
    private LoggerInterface $logger;
    private ElabftwService $service;
    private ConnectedAppRepository $connectedAppRepository;
    private EntityManagerInterface $em;
    private SyncCommandProcessor $processor;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = $this->createMock(ElabftwService::class);
        $this->connectedAppRepository = $this->createMock(ConnectedAppRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->processor = new SyncCommandProcessor(
            $this->logger,
            $this->service,
            $this->connectedAppRepository,
            $this->em
        );
    }

    #[Test]
    public function processReturnsWhenNoAppsFound(): void
    {
        $this->connectedAppRepository->expects($this->once())
            ->method('findBy')
            ->with(['code' => AppCode::Elabftw])
            ->willReturn([])
        ;

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('No Elabftw connected apps found.')
        ;

        $this->processor->process();
    }

    // NEED TO BE HANDLED
    //    #[Test]
    //    public function processSyncsApps(): void
    //    {
    //        $app1 = $this->createMock(ConnectedApp::class);
    //        $app2 = $this->createMock(ConnectedApp::class);
    //
    //        $this->connectedAppRepository->expects($this->once())
    //            ->method('findBy')
    //            ->with(['code' => AppCode::Elabftw])
    //            ->willReturn([$app1, $app2])
    //        ;
    //
    //        $io = $this->createMock(SymfonyStyle::class);
    //        $io->expects($this->once())->method('text');
    //        $io->expects($this->once())->method('progressStart')->with(2);
    //
    //        $matcher = $this->exactly(2);
    //        $this->service->expects($matcher)
    //            ->method('syncAllToExternal')
    //            ->willReturnCallback(function ($app, $class) use ($matcher, $app1, $app2): void {
    //                match ($matcher->numberOfInvocations()) {
    //                    1 => $this->assertSame([$app1, Experiment::class], [$app, $class]),
    //                    2 => $this->assertSame([$app2, Experiment::class], [$app, $class]),
    //                };
    //            })
    //        ;
    //
    //        $app1->expects($this->once())->method('setLastSyncAt');
    //        $app2->expects($this->once())->method('setLastSyncAt');
    //
    //        $this->em->expects($this->exactly(2))->method('flush');
    //
    //        $this->processor->process($io);
    //    }

    #[Test]
    public function processHandlesExceptions(): void
    {
        $this->connectedAppRepository->expects($this->once())
            ->method('findBy')
            ->willThrowException(new \RuntimeException('DB Error'))
        ;

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Error during synchronization: DB Error')
        ;

        $this->processor->process();
    }
}
