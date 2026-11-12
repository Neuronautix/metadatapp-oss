<?php

declare(strict_types=1);

namespace App\Security\Core;

use App\Entity\Account;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Roles;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @implements AttributesBasedUserProviderInterface<UserInterface|User>
 */
final readonly class UserProvider implements AttributesBasedUserProviderInterface
{
    private const array ACCOUNT_KEY_CLAIMS = ['account_key', 'organization', 'organization_id', 'account'];
    private const array ACCOUNT_NAME_CLAIMS = ['account_name', 'organization_name'];
    private const array ORGANIZATION_ROLE_CLAIMS = ['organization_role', 'account_role'];

    public function __construct(private ManagerRegistry $registry, private UserRepository $repository)
    {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        $manager = $this->registry->getManagerForClass($user::class);
        if (!$manager) {
            throw new UnsupportedUserException(\sprintf('User class "%s" not supported.', $user::class));
        }

        $manager->refresh($user);

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }

    /**
     * Create or update User on login.
     *
     * @param array<string, mixed> $attributes
     */
    public function loadUserByIdentifier(string $identifier, array $attributes = []): UserInterface
    {
        $user = $this->repository->findOneBy(['email' => $identifier]) ?: new User();
        $user->email = $identifier;

        if (!isset($attributes['given_name'])) {
            throw new UnsupportedUserException('Property "given_name" is missing in token attributes.');
        }
        $user->firstName = $attributes['given_name'];

        if (!isset($attributes['family_name'])) {
            throw new UnsupportedUserException('Property "family_name" is missing in token attributes.');
        }
        $user->lastName = $attributes['family_name'];

        $user->setAccount($this->resolveAccount($user, $attributes));
        $user->setRoles($this->resolveRoles($user, $attributes));

        $this->repository->save($user, true);

        return $user;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function resolveAccount(User $user, array $attributes): Account
    {
        /** @var ObjectRepository<Account> $accountRepository */
        $accountRepository = $this->registry->getRepository(Account::class);

        $accountKey = $this->extractFirstStringClaim($attributes, self::ACCOUNT_KEY_CLAIMS);
        if (null !== $accountKey) {
            $account = $accountRepository->findOneBy(['externalKey' => $accountKey]);
            if ($account instanceof Account) {
                return $account;
            }

            throw new UnsupportedUserException(\sprintf('No account matches external key "%s".', $accountKey));
        }

        $accountName = $this->extractFirstStringClaim($attributes, self::ACCOUNT_NAME_CLAIMS);
        if (null !== $accountName) {
            $account = $accountRepository->findOneBy(['name' => $accountName]);
            if ($account instanceof Account) {
                return $account;
            }

            throw new UnsupportedUserException(\sprintf('No account matches name "%s".', $accountName));
        }

        if ($user->hasAccount()) {
            return $user->getAccount();
        }

        throw new UnsupportedUserException('Token attributes must include an account identifier for first login.');
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string>
     */
    private function resolveRoles(User $user, array $attributes): array
    {
        $organizationRole = $this->extractFirstStringClaim($attributes, self::ORGANIZATION_ROLE_CLAIMS);

        return match ($organizationRole) {
            // User::setRoles() guarantees ROLE_USER on every persisted user.
            'admin' => [Roles::ROLE_ADMIN],
            'editor', 'viewer' => [Roles::ROLE_USER],
            null => $user->getRoles(),
            default => throw new UnsupportedUserException(\sprintf('Unsupported organization role "%s".', $organizationRole)),
        };
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, string>   $claimNames
     */
    private function extractFirstStringClaim(array $attributes, array $claimNames): ?string
    {
        foreach ($claimNames as $claimName) {
            if (!\array_key_exists($claimName, $attributes)) {
                continue;
            }

            $claim = $attributes[$claimName];
            if (\is_array($claim)) {
                $claim = $claim[0] ?? null;
            }

            if (\is_string($claim)) {
                $trimmedClaim = trim($claim);
                if ('' !== $trimmedClaim) {
                    return $trimmedClaim;
                }
            }
        }

        return null;
    }
}
