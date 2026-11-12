<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Account;
use App\Entity\User;
use App\Hcm\HcmMetadataFormSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Materialises the HCM Minimal Metadata Form (CDEs + CanonicalForm + sections +
 * fields) from its canonical JSON definition. Targets a specific account when
 * `--account` is given (UUID or name), otherwise the first available account.
 * Safe to re-run: seeding is idempotent per account.
 */
#[AsCommand(
    name: 'app:hcm:seed-form',
    description: 'Seeds the HCM Minimal Metadata Form from its canonical definition.',
)]
final class HcmMetadataFormSeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HcmMetadataFormSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'account',
            null,
            InputOption::VALUE_REQUIRED,
            'Target account by UUID or name (defaults to the first available account).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding HCM Minimal Metadata Form');

        $accountRef = $input->getOption('account');
        $account = $this->resolveAccount(\is_string($accountRef) ? trim($accountRef) : null);
        if (null === $account) {
            $io->error(null === $accountRef
                ? 'No user/account found. Load base fixtures first.'
                : \sprintf('No account matched "%s" (looked up by UUID then name).', (string) $accountRef));

            return Command::FAILURE;
        }

        $io->text(\sprintf('Target account: %s (%s)', $account->getName(), (string) $account->getId()));

        $summary = $this->seeder->seed($account);

        if (!$summary->created) {
            $io->success('HCM Minimal Metadata Form already present for this account; nothing to do.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf(
            'Seeded HCM Minimal Metadata Form: %d CDEs, %d sections, %d canonical fields.',
            $summary->cdes,
            $summary->sections,
            $summary->fields,
        ));

        return Command::SUCCESS;
    }

    /**
     * Resolve the account to seed: by UUID or name when a reference is given,
     * otherwise the account of the first available user (legacy behaviour).
     */
    private function resolveAccount(?string $reference): ?Account
    {
        $accounts = $this->entityManager->getRepository(Account::class);

        if (null === $reference || '' === $reference) {
            return $this->entityManager->getRepository(User::class)->findOneBy([])?->getAccount();
        }

        if (Uuid::isValid($reference)) {
            $byId = $accounts->find($reference);
            if (null !== $byId) {
                return $byId;
            }
        }

        return $accounts->findOneBy(['name' => $reference]);
    }
}
