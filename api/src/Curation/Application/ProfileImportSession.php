<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Entity\ImportColumn;
use App\Entity\ImportRow;
use App\Entity\ImportSession;
use App\Enum\ImportSessionStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ProfileImportSession
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Idempotency rule: safe to repeat and recalculates rows/columns for the same session.
     */
    public function execute(ImportSession $session, array $columns, array $rows): void
    {
        // 1. Clear existing for safe repeat if status allows
        $this->entityManager->createQuery('DELETE FROM App\Entity\ImportRow r WHERE r.session = :session')
            ->setParameter('session', $session)
            ->execute()
        ;
        $this->entityManager->createQuery('DELETE FROM App\Entity\ImportColumn c WHERE c.session = :session')
            ->setParameter('session', $session)
            ->execute()
        ;

        // 2. Re-create columns
        foreach ($columns as $index => $colData) {
            $col = new ImportColumn();
            $col->setSession($session);
            $col->setColumnIndex($index);
            $col->setHeaderName($colData['header']);
            $col->setSampleValues($colData['samples'] ?? []);
            $this->entityManager->persist($col);
        }

        // 3. Re-create rows
        foreach ($rows as $index => $rowData) {
            $row = new ImportRow();
            $row->setSession($session);
            $row->setRowIndex($index);
            $row->setSourcePayload($rowData);
            $row->setRowHash(md5((string) json_encode($rowData)));
            $this->entityManager->persist($row);
        }

        $session->setSourceFileFingerprint(md5((string) json_encode($rows)));
        $session->setStatus(ImportSessionStatus::Profiled);
        $session->setProfiledAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}
