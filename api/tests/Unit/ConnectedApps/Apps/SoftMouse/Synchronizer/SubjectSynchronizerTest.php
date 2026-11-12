<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\SoftMouse\Synchronizer;

use App\ConnectedApps\Apps\SoftMouse\Client\ClientInterface;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\AnimalDto;
use App\ConnectedApps\Apps\SoftMouse\Client\Resources\Animals;
use App\ConnectedApps\Apps\SoftMouse\Mapper\AnimalMapper;
use App\ConnectedApps\Apps\SoftMouse\Synchronizer\SubjectSynchronizer;
use App\Entity\Account;
use App\Entity\ConnectedApp;
use App\Entity\Subject;
use App\Repository\SubjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SubjectSynchronizerTest extends TestCase
{
    private ClientInterface $client;
    private Animals $animalsResource;
    private EntityManagerInterface $em;
    private SubjectRepository $subjectRepository;
    private AnimalMapper $animalMapper;
    private SubjectSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->animalsResource = $this->createMock(Animals::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->subjectRepository = $this->createMock(SubjectRepository::class);
        $this->animalMapper = $this->createMock(AnimalMapper::class);

        $this->synchronizer = new SubjectSynchronizer(
            $this->client,
            $this->em,
            $this->subjectRepository,
            $this->animalMapper
        );
    }

    #[Test]
    public function syncSubjectsReturnsNullWhenNoAnimalsReturned(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);

        $this->client->expects($this->once())
            ->method('animals')
            ->willReturn($this->animalsResource)
        ;

        $this->animalsResource->expects($this->once())
            ->method('search')
            ->willReturn([])
        ;

        $result = $this->synchronizer->syncSubjects($connectedApp);

        $this->assertNull($result);
    }

    #[Test]
    public function syncSubjectsSyncsNewSubject(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $account = $this->createMock(Account::class);
        $connectedApp->method('getAccount')->willReturn($account);

        $animalDto = new AnimalDto(
            123,
            null,
            '2023-01-01',
            '2023-01-01T00:00:00Z',
            'Strain'
        );

        $this->client->expects($this->once())
            ->method('animals')
            ->willReturn($this->animalsResource)
        ;

        $this->animalsResource->expects($this->once())
            ->method('search')
            ->willReturn([$animalDto])
        ;

        $this->subjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['softmouseExternalId' => 123])
            ->willReturn(null)
        ;

        $this->em->expects($this->exactly(2))
            ->method('persist')
            ->with($this->isInstanceOf(Subject::class))
        ;

        $this->animalMapper->expects($this->once())
            ->method('mapFromDto')
            ->with($animalDto, $this->isInstanceOf(Subject::class))
        ;

        $result = $this->synchronizer->syncSubjects($connectedApp);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Subject::class, $result[0]);
        $this->assertSame('123', $result[0]->getSoftmouseExternalId());
    }

    #[Test]
    public function syncSubjectsSyncsExistingSubject(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $account = $this->createMock(Account::class);
        $connectedApp->method('getAccount')->willReturn($account);

        $animalDto = new AnimalDto(
            456,
            null,
            '2023-01-01',
            '2023-01-01T00:00:00Z',
            'Strain'
        );

        $subject = new Subject();

        $this->client->expects($this->once())
            ->method('animals')
            ->willReturn($this->animalsResource)
        ;

        $this->animalsResource->expects($this->once())
            ->method('search')
            ->willReturn([$animalDto])
        ;

        $this->subjectRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['softmouseExternalId' => 456])
            ->willReturn($subject)
        ;

        $this->em->expects($this->once())
            ->method('persist')
            ->with($subject)
        ;

        $this->animalMapper->expects($this->once())
            ->method('mapFromDto')
            ->with($animalDto, $subject)
        ;

        $result = $this->synchronizer->syncSubjects($connectedApp);

        $this->assertCount(1, $result);
        $this->assertSame($subject, $result[0]);
    }

    #[Test]
    public function syncOneFromReturnsNullIfSubjectNotFound(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();

        $this->subjectRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn(null)
        ;

        $result = $this->synchronizer->syncOneFrom($connectedApp, $uuid);

        $this->assertNull($result);
    }

    #[Test]
    public function syncOneFromReturnsNullIfExternalDataNotFound(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $uuid = Uuid::v4();
        $subject = $this->createMock(Subject::class);
        $subject->method('getSoftmouseExternalId')->willReturn('789');

        $this->subjectRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($subject)
        ;

        $this->client->expects($this->once())
            ->method('animals')
            ->willReturn($this->animalsResource)
        ;

        $this->animalsResource->expects($this->once())
            ->method('get')
            ->with('789')
            ->willReturn(null)
        ;

        $result = $this->synchronizer->syncOneFrom($connectedApp, $uuid);

        $this->assertNull($result);
    }

    #[Test]
    public function syncOneFromSuccess(): void
    {
        $connectedApp = $this->createMock(ConnectedApp::class);
        $account = $this->createMock(Account::class);
        $connectedApp->method('getAccount')->willReturn($account);

        $uuid = Uuid::v4();
        $subject = new Subject();
        $subject->setSoftmouseExternalId('999');

        $animalDto = new AnimalDto(
            999,
            null,
            '2023-01-01',
            '2023-01-01T00:00:00Z',
            'Strain'
        );

        $this->subjectRepository->expects($this->once())
            ->method('find')
            ->with($uuid)
            ->willReturn($subject)
        ;

        $this->client->expects($this->once())
            ->method('animals')
            ->willReturn($this->animalsResource)
        ;

        $this->animalsResource->expects($this->once())
            ->method('get')
            ->with('999')
            ->willReturn($animalDto)
        ;

        $this->animalMapper->expects($this->once())
            ->method('mapFromDto')
            ->with($animalDto, $subject)
        ;

        $this->em->expects($this->once())
            ->method('persist')
            ->with($subject)
        ;

        $this->synchronizer->syncOneFrom($connectedApp, $uuid);
    }
}
