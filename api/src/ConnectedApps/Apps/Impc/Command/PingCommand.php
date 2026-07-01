<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Impc\Command;

use App\ConnectedApps\Apps\Impc\Client\ImpcClientInterface;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use App\Repository\ConnectedAppRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Quick live verification against the IMPReSS (IMPC) standardized-screen catalogue.
 *
 * Unlike the functional test suite (which decorates the client with a mock under
 * `when@test`), this command runs in dev/prod and therefore hits the real API,
 * making it a convenient smoke test that connectivity, base URL, and response
 * shapes are working end to end.
 */
#[AsCommand(
    name: 'app:impc:ping',
    description: 'Ping the live IMPReSS (IMPC) API to verify connectivity and response shapes.',
)]
final class PingCommand extends Command
{
    public function __construct(
        private readonly ConnectedAppRepository $connectedAppRepository,
        private readonly ImpcClientInterface $client,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('app-id', InputArgument::OPTIONAL, 'ConnectedApp UUID to ping. If omitted, every IMPReSS app is pinged.')
            ->addOption('search', null, InputOption::VALUE_REQUIRED, 'Procedure search term to exercise the procedures endpoint.', 'open field')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('IMPReSS (IMPC) live ping');

        $appId = $input->getArgument('app-id');
        $searchTerm = (string) $input->getOption('search');

        $connectedApps = $this->resolveApps(\is_string($appId) ? $appId : null, $io);
        if (null === $connectedApps) {
            return Command::FAILURE;
        }

        if ([] === $connectedApps) {
            $io->warning('No IMPReSS connected apps found. Create one first (AppCode "impc").');

            return Command::SUCCESS;
        }

        $hadFailure = false;

        foreach ($connectedApps as $connectedApp) {
            $io->section(\sprintf('App %s (%s)', (string) $connectedApp->getId(), $connectedApp->getName()));
            $io->writeln(\sprintf('Base URL: <info>%s</info>', $this->client->getBaseUrl($connectedApp)));

            try {
                $pipelines = $this->client->listPipelines($connectedApp, 1, 0);
                $io->writeln(\sprintf('Pipelines endpoint OK — totalItems: <info>%d</info>', $pipelines['totalItems']));

                $procedures = $this->client->searchProcedures($connectedApp, $searchTerm, 3, 0);
                $io->writeln(\sprintf('Procedure search "%s" OK — totalItems: <info>%d</info>, returned: %d', $searchTerm, $procedures['totalItems'], \count($procedures['procedures'])));

                $io->success(\sprintf('IMPReSS reachable for %s', (string) $connectedApp->getId()));
            } catch (\Throwable $e) {
                $hadFailure = true;
                $io->error(\sprintf('Ping failed for %s: %s', (string) $connectedApp->getId(), $e->getMessage()));
            }
        }

        return $hadFailure ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return list<ConnectedApp>|null null signals an error that should fail the command
     */
    private function resolveApps(?string $appId, SymfonyStyle $io): ?array
    {
        if (null === $appId) {
            return $this->connectedAppRepository->findBy(['code' => AppCode::Impc]);
        }

        $connectedApp = $this->connectedAppRepository->find($appId);
        if (!$connectedApp instanceof ConnectedApp) {
            $io->error(\sprintf('No connected app found with id %s.', $appId));

            return null;
        }

        if (AppCode::Impc !== $connectedApp->getCode()) {
            $io->error(\sprintf('Connected app %s is not an IMPReSS app (code: %s).', $appId, $connectedApp->getCode()->value));

            return null;
        }

        return [$connectedApp];
    }
}
