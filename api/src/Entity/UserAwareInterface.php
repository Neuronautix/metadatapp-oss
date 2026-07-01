<?php

declare(strict_types=1);

namespace App\Entity;

use Symfony\Component\Uid\Uuid;

interface UserAwareInterface
{
    public function getId(): ?Uuid;

    public function getUser(): User;

    public function setUser(User $user): self;
}
