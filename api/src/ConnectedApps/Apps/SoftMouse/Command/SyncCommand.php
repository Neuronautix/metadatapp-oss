<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Command;

use App\ConnectedApps\Apps\SoftMouse\SoftMouseService;
use App\Enum\AppCode;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync:softmouse',
    description: 'Synchronise data from SoftMouse into MAPP entities',
)]
class SyncCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private SoftMouseService $softMouseService,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email of the user whose SoftMouse connected app should be synchronized')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Simulate the synchronization process without persisting changes')
        ;
    }

    /**
     * @throws \Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('SoftMouse ➜ Synchronisation (Batched)');
        $io->text($dryRun ? 'Running in dry-run mode. No changes will be persisted.' : 'Running in live mode.');

        $user = $this->userRepository->findOneBy(['email' => $input->getArgument('email')]);
        if (null === $user) {
            $io->error('User not found.');

            return Command::FAILURE;
        }
        $softMouse = $user->getConnectedAppByCode(AppCode::SoftMouse);

        if (null === $softMouse) {
            $io->error('SoftMouse connected app not found for the user.');

            return Command::FAILURE;
        }

        // if we want to test the sync trigger via messenger:
        //        $this->bus->dispatch(new SyncFullFromAppMessage($softMouse->getId()));

        $this->softMouseService->sync($softMouse);
        $this->em->flush();

        $io->success('Synchronization completed successfully.');

        return Command::SUCCESS;
    }
}
