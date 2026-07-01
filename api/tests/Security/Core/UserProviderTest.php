<?php

declare(strict_types=1);

namespace App\Tests\Security\Core;

use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\Account;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Core\UserProvider;
use App\Security\Roles;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

final class UserProviderTest extends TestCase
{
    #[Test]
    public function it_maps_account_and_admin_role_from_keycloak_claims(): void
    {
        $account = AccountFactory::new([
            'name' => 'Inst.@CorpOGN',
            'externalKey' => 'inst-corpogn',
        ])->make();

        $accountRepository = $this->createMock(ObjectRepository::class);
        $accountRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['externalKey' => 'inst-corpogn'])
            ->willReturn($account);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects(self::once())
            ->method('getRepository')
            ->with(Account::class)
            ->willReturn($accountRepository);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'doctor.yiolyo@mdta.net'])
            ->willReturn(null);
        $userRepository
            ->expects(self::once())
            ->method('save')
            ->with(
                self::callback(static function (User $user) use ($account): bool {
                    self::assertSame('doctor.yiolyo@mdta.net', $user->getEmail());
                    self::assertSame('Doctor', $user->getFirstName());
                    self::assertSame('YioLyo', $user->getLastName());
                    self::assertSame($account, $user->getAccount());
                    self::assertSame([Roles::ROLE_USER, Roles::ROLE_ADMIN], $user->getRoles());

                    return true;
                }),
                true,
            );

        $provider = new UserProvider($registry, $userRepository);

        $provider->loadUserByIdentifier('doctor.yiolyo@mdta.net', [
            'given_name' => 'Doctor',
            'family_name' => 'YioLyo',
            'account_key' => 'inst-corpogn',
            'organization_role' => 'admin',
        ]);
    }

    #[Test]
    public function it_keeps_existing_account_when_the_token_has_no_account_claim(): void
    {
        $account = AccountFactory::new([
            'name' => 'Inst.@CorpOGN',
            'externalKey' => 'inst-corpogn',
        ])->make();

        $existingUser = UserFactory::new([
            'email' => 'viewer@mdta.net',
            'firstName' => 'View',
            'lastName' => 'Only',
            'account' => $account,
            'roles' => [Roles::ROLE_ADMIN],
        ])->make();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects(self::once())
            ->method('getRepository')
            ->with(Account::class)
            ->willReturn($this->createMock(ObjectRepository::class));

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'viewer@mdta.net'])
            ->willReturn($existingUser);
        $userRepository
            ->expects(self::once())
            ->method('save')
            ->with(
                self::callback(static function (User $user) use ($account): bool {
                    self::assertSame($account, $user->getAccount());
                    self::assertSame([Roles::ROLE_USER], $user->getRoles());

                    return true;
                }),
                true,
            );

        $provider = new UserProvider($registry, $userRepository);

        $provider->loadUserByIdentifier('viewer@mdta.net', [
            'given_name' => 'View',
            'family_name' => 'Only',
            'organization_role' => 'viewer',
        ]);
    }

    #[Test]
    public function it_requires_an_account_identifier_for_first_login(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry
            ->expects(self::once())
            ->method('getRepository')
            ->with(Account::class)
            ->willReturn($this->createMock(ObjectRepository::class));

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'new.user@mdta.net'])
            ->willReturn(null);
        $userRepository
            ->expects(self::never())
            ->method('save');

        $provider = new UserProvider($registry, $userRepository);

        $this->expectException(UnsupportedUserException::class);
        $this->expectExceptionMessage('Token attributes must include an account identifier for first login.');

        $provider->loadUserByIdentifier('new.user@mdta.net', [
            'given_name' => 'New',
            'family_name' => 'User',
            'organization_role' => 'editor',
        ]);
    }
}
