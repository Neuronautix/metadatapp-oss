<?php

declare(strict_types=1);

namespace App\Entity;

use Symfony\Component\Uid\Uuid;

interface AccountAwareInterface
{
    public function getId(): ?Uuid;

    public function getAccount(): Account;

    public function setAccount(Account $account): self;
}
