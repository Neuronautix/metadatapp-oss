<?php

declare(strict_types=1);

namespace App\Zefix\Alerts\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Alert;
use App\Entity\User;
use App\Enum\AlertStatus;
use App\Zefix\Alerts\ZefixAlert;
use App\Zefix\Alerts\ZefixAlertViewFactory;
use App\Zefix\Support\ZefixPermissionChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<ZefixAlert, ZefixAlert>
 */
final readonly class ZefixAlertActionProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ZefixAlertViewFactory $viewFactory,
        private Security $security,
        private RequestStack $requestStack,
        private ZefixPermissionChecker $permissionChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ZefixAlert
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User is not authenticated.');
        }

        $this->permissionChecker->assertCanEdit($user);

        $alert = $this->entityManager->getRepository(Alert::class)->findOneBy([
            'id' => (string) ($uriVariables['alertID'] ?? ''),
            'account' => $user->getAccount(),
        ]);
        if (!$alert instanceof Alert) {
            throw new NotFoundHttpException('Zefix alert not found.');
        }

        $now = new \DateTimeImmutable();
        if (str_contains((string) $this->requestStack->getCurrentRequest()?->getPathInfo(), '/acknowledge')) {
            if (AlertStatus::ACTIVE === $alert->getStatus()) {
                $alert
                    ->setStatus(AlertStatus::ACKNOWLEDGED)
                    ->setAcknowledgedAt($now)
                ;
            }
        } else {
            if (AlertStatus::RESOLVED !== $alert->getStatus()) {
                $alert
                    ->setStatus(AlertStatus::RESOLVED)
                    ->setResolvedAt($now)
                ;
            }
        }

        $this->entityManager->flush();

        return $this->viewFactory->create($alert);
    }
}
