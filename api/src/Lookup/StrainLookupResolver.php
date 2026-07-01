<?php

declare(strict_types=1);

namespace App\Lookup;

use App\Entity\Strain;
use App\Repository\StrainRepository;
use Doctrine\ORM\EntityManagerInterface;

class StrainLookupResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StrainRepository $strainRepository,
    ) {
    }

    public function resolve(string $label, ?string $externalId = null): Strain
    {
        $label = trim($label);
        if ('' === $label) {
            throw new \InvalidArgumentException('A strain label is required.');
        }

        $strain = null;
        if (null !== $externalId) {
            $strain = $this->strainRepository->findOneBy(['link' => $externalId]);
        }

        $strain ??= $this->strainRepository->findOneByNormalizedName($label);

        if (null === $strain) {
            $strain = (new Strain())
                ->setName($label)
                ->setLink($externalId)
            ;

            $this->entityManager->persist($strain);
        } elseif (null !== $externalId && null === $strain->getLink()) {
            $strain->setLink($externalId);
        }

        $this->entityManager->flush();

        return $strain;
    }
}
