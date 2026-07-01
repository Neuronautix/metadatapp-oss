<?php

declare(strict_types=1);

namespace App\Zefix\Alerts\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Enum\AlertStatus;
use App\Repository\AlertRepository;
use App\Zefix\Alerts\ZefixAlert;
use App\Zefix\Alerts\ZefixAlertViewFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProviderInterface<ZefixAlert>
 */
final readonly class ZefixAlertProvider implements ProviderInterface
{
    public function __construct(
        private AlertRepository $alertRepository,
        private ZefixAlertViewFactory $viewFactory,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('User is not authenticated.');
        }

        $status = AlertStatus::tryFrom((string) $this->requestStack->getCurrentRequest()?->query->get('status', ''));

        return array_map(
            $this->viewFactory->create(...),
            $this->alertRepository->findForAccount($user->getAccount(), $status)
        );
    }
}
