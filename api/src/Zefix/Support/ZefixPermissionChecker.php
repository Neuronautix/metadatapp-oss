<?php

declare(strict_types=1);

namespace App\Zefix\Support;

use App\Entity\User;
use App\Security\Roles;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class ZefixPermissionChecker
{
    public function canEdit(User $user): bool
    {
        return $user->hasRole(Roles::ROLE_ADMIN) || $user->hasRole(Roles::ROLE_SUPER_ADMIN);
    }

    public function canAdminister(User $user): bool
    {
        return $user->hasRole(Roles::ROLE_SUPER_ADMIN);
    }

    public function assertCanEdit(User $user): void
    {
        if (!$this->canEdit($user)) {
            throw new AccessDeniedHttpException('Zefix editing requires elevated access.');
        }
    }

    public function assertCanAdminister(User $user): void
    {
        if (!$this->canAdminister($user)) {
            throw new AccessDeniedHttpException('Zefix administration requires elevated access.');
        }
    }
}
