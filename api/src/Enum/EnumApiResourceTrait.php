<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\Serializer\Attribute\Groups;

trait EnumApiResourceTrait
{
    public function getId(): string
    {
        return (string) $this->value;
    }

    #[Groups('enum:read')]
    public function getValue(): ?string
    {
        return $this->value;
    }

    public static function getCases(): array
    {
        return self::cases();
    }

    public static function getCase(Operation $operation, array $uriVariables): ?static
    {
        $name = $uriVariables['id'] ?? null;

        if (null === $name) {
            return null;
        }

        $backingType = (new \ReflectionEnum(self::class))->getBackingType();
        if ($backingType && 'int' === $backingType->getName() && is_numeric($name)) {
            return self::tryFrom((int) $name);
        }

        return self::tryFrom($name);
    }
}
