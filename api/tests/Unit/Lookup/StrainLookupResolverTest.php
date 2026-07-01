<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lookup;

use App\Entity\Strain;
use App\Lookup\StrainLookupResolver;
use App\Repository\StrainRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StrainLookupResolverTest extends TestCase
{
    #[Test]
    public function itReturnsExistingStrainFoundByExternalId(): void
    {
        $strain = (new Strain())
            ->setName('C57BL/6J')
            ->setLink('http://www.ebi.ac.uk/efo/EFO_0004472')
        ;

        $repository = $this->createMock(StrainRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['link' => 'http://www.ebi.ac.uk/efo/EFO_0004472'])
            ->willReturn($strain)
        ;
        $repository
            ->expects($this->never())
            ->method('findOneByNormalizedName')
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $resolver = new StrainLookupResolver($entityManager, $repository);

        $resolved = $resolver->resolve('C57BL/6J', 'http://www.ebi.ac.uk/efo/EFO_0004472');

        $this->assertSame($strain, $resolved);
    }

    #[Test]
    public function itUpdatesExistingStrainFoundByName(): void
    {
        $strain = (new Strain())
            ->setName('C57BL/6J')
        ;

        $repository = $this->createMock(StrainRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['link' => 'http://www.ebi.ac.uk/efo/EFO_0004472'])
            ->willReturn(null)
        ;
        $repository
            ->expects($this->once())
            ->method('findOneByNormalizedName')
            ->with('C57BL/6J')
            ->willReturn($strain)
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $resolver = new StrainLookupResolver($entityManager, $repository);

        $resolved = $resolver->resolve('C57BL/6J', 'http://www.ebi.ac.uk/efo/EFO_0004472');

        $this->assertSame($strain, $resolved);
        $this->assertSame('http://www.ebi.ac.uk/efo/EFO_0004472', $resolved->getLink());
    }

    #[Test]
    public function itCreatesANewStrainWhenNoExistingRecordMatches(): void
    {
        $repository = $this->createMock(StrainRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['link' => 'http://www.ebi.ac.uk/efo/EFO_0004472'])
            ->willReturn(null)
        ;
        $repository
            ->expects($this->once())
            ->method('findOneByNormalizedName')
            ->with('C57BL/6J')
            ->willReturn(null)
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Strain::class))
        ;
        $entityManager->expects($this->once())->method('flush');

        $resolver = new StrainLookupResolver($entityManager, $repository);

        $resolved = $resolver->resolve('C57BL/6J', 'http://www.ebi.ac.uk/efo/EFO_0004472');

        $this->assertSame('C57BL/6J', $resolved->getName());
        $this->assertSame('http://www.ebi.ac.uk/efo/EFO_0004472', $resolved->getLink());
    }
}
