<?php

declare(strict_types=1);

namespace App\Tests\Functional\ConnectedApps\Reference;

use App\ConnectedApps\Reference\ReferenceImportService;
use App\DataFixtures\Factory\UserFactory;
use App\Repository\ImportedReferenceRepository;
use App\Security\Roles;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ReferenceImportServiceTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    public function testImportUpsertsIdempotentlyAndPreservesProvenance(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $container->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $service = $container->get(ReferenceImportService::class);
        $repository = $container->get(ImportedReferenceRepository::class);

        $item = [
            'id' => 'jax_phenome:measure:11107',
            'source' => 'jax_phenome',
            'sourceName' => 'JAX Phenome (MPD)',
            'type' => 'measure',
            'title' => 'glucose (plasma)',
            'externalUrl' => 'https://phenome.jax.org/measureset/11107',
            'identifiers' => ['measnum' => 11107],
            'raw' => ['measnum' => 11107, 'descrip' => 'glucose (plasma)'],
        ];

        $service->import([$item]);
        $service->import([array_merge($item, ['title' => 'glucose (plasma) — updated'])]);

        $stored = $repository->findBy(['account' => $user->getAccount()]);
        $this->assertCount(1, $stored, 'Re-importing the same reference must update, not duplicate.');
        $reference = $stored[0];
        $this->assertSame('glucose (plasma) — updated', $reference->getTitle());
        $this->assertSame('https://phenome.jax.org/measureset/11107', $reference->getExternalUrl());
        $this->assertSame(['measnum' => 11107], $reference->getIdentifiers());
        $this->assertSame('measure', $reference->getType());
        $this->assertSame($user->getAccount()->getId(), $reference->getAccount()->getId());
    }

    public function testImportSkipsItemsWithoutAnId(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $container->get('security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $saved = $container->get(ReferenceImportService::class)->import([['title' => 'no id here']]);

        $this->assertSame([], $saved);
    }
}
