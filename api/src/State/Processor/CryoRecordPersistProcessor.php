<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Account;
use App\Entity\CryoRecord;
use App\Entity\User;
use App\Entity\ZebrafishLine;
use App\Zefix\Support\ZefixPermissionChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/** @implements ProcessorInterface<CryoRecord, CryoRecord> */
final readonly class CryoRecordPersistProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<CryoRecord, CryoRecord> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $entityManager,
        private ZefixPermissionChecker $permissionChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CryoRecord
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User is not authenticated.');
        }

        $this->permissionChecker->assertCanEdit($user);

        $line = $this->loadLineForCurrentAccount((string) ($uriVariables['lineID'] ?? ''));

        $data->setLine($line);
        $data->setAccount($line->getAccount());

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function loadLineForCurrentAccount(string $lineID): ZebrafishLine
    {
        try {
            $uuid = Uuid::fromString($lineID);
        } catch (\Throwable) {
            throw new NotFoundHttpException('Zefix line not found.');
        }

        $line = $this->entityManager->getRepository(ZebrafishLine::class)
            ->createQueryBuilder('line')
            ->andWhere('line.account = :account')
            ->andWhere('line.id = :lineID')
            ->setParameter('account', $this->requireUserAccount())
            ->setParameter('lineID', $uuid)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        if (!$line instanceof ZebrafishLine) {
            throw new NotFoundHttpException('Zefix line not found.');
        }

        return $line;
    }

    private function requireUserAccount(): Account
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User is not authenticated.');
        }

        return $user->getAccount();
    }
}
