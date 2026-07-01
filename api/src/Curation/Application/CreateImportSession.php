<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Entity\Account;
use App\Entity\ImportSession;
use App\Entity\User;
use App\Enum\ImportSessionStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CreateImportSession
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Idempotency rule: CreateImportSession creates one session per upload and stores the source file fingerprint.
     */
    public function execute(string $sourceFileFingerprint, Account $account, User $user): ImportSession
    {
        // For idempotency, check if session already exists for this fingerprint
        $existing = $this->entityManager->getRepository(ImportSession::class)
            ->findOneBy(['sourceFileFingerprint' => $sourceFileFingerprint, 'account' => $account])
        ;

        if ($existing) {
            return $existing;
        }

        $session = new ImportSession();
        $session->setSourceFileFingerprint($sourceFileFingerprint);
        $session->setStatus(ImportSessionStatus::Uploaded);

        /** @var Account $managedAccount */
        $managedAccount = $this->entityManager->getReference(Account::class, $account->getId());
        /** @var User $managedUser */
        $managedUser = $this->entityManager->getReference(User::class, $user->getId());

        $session->setAccount($managedAccount);
        $session->setUser($managedUser);

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }
}
