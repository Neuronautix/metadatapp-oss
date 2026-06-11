<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Command;

use App\ConnectedApps\Apps\Fair3r\Client\Client as Fair3rClient;
use App\ConnectedApps\Apps\Fair3r\Client\Fair3rHttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fair3r:datastore:delete-all',
    description: 'Delete all Fair3R datastores by chaining package_list → package_show → datastore_delete',
)]
class DatastoreDeleteAllCommand extends Command
{
    public function __construct(
        private readonly Fair3rClient $client,
        private readonly Fair3rHttpClientInterface $http,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Simulate deletions without calling datastore_delete')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Fair3R datastore purge');
        $io->text($dryRun ? 'Dry-run: will list targets but not delete.' : 'Live mode: will delete all matching datastores.');

        try {
            // 1) package_list → returns dataset names
            $listResponse = $this->http->sendRequest('GET', 'action/package_list');
            $this->logger->info('package_list response', ['response' => $listResponse]);
            $datasetNames = $listResponse->result;

            if (0 === \count($datasetNames)) {
                $io->warning('No datasets returned by package_list. Nothing to delete.');

                return Command::SUCCESS;
            }

            $io->section('Datasets discovered');
            foreach ($datasetNames as $name) {
                $io->writeln('- ' . (string) $name);
            }

            $deletedCount = 0;
            $resourceCount = 0;

            // 2) package_show for each dataset → find resources
            foreach ($datasetNames as $name) {
                try {
                    $dataset = $this->client->datasets()->show((string) $name);
                } catch (\Throwable $e) {
                    $io->warning(\sprintf('Error fetching dataset %s: %s', (string) $name, $e->getMessage()));
                    continue;
                }
                if (null === $dataset) {
                    $io->warning(\sprintf('Skipping dataset %s: not found or invalid payload.', (string) $name));
                    continue;
                }
                $this->logger->info('package_show response', ['dataset' => $dataset]);

                $resources = $dataset->resources ?? [];
                if (0 === \count($resources)) {
                    $io->writeln(\sprintf('No resources for dataset %s', (string) $name));
                    continue;
                }

                // 3) datastore_delete for each resource (full wipe: no filters)
                foreach ($resources as $resource) {
                    $resourceId = (string) $resource->id;
                    if ('' === $resourceId) {
                        $io->warning(\sprintf('Skipping resource without id in dataset %s', (string) $name));
                        continue;
                    }

                    ++$resourceCount;
                    if ($dryRun) {
                        $io->writeln(\sprintf('[dry-run] Would delete datastore for resource_id=%s', $resourceId));
                        continue;
                    }

                    try {
                        // Empty filters array → delete all records for the resource
                        $this->client->datastores()->delete($resourceId, []);
                    } catch (\Throwable $e) {
                        $io->warning(\sprintf('Resource %s not found or inaccessible: %s', $resourceId, $e->getMessage()));
                        continue;
                    }
                    ++$deletedCount;
                    $io->writeln(\sprintf('Deleted datastore records for resource_id=%s', $resourceId));
                }
            }

            $io->success(\sprintf(
                'Done. Resources inspected: %d, Datastore deletions executed: %d%s',
                $resourceCount,
                $deletedCount,
                $dryRun ? ' (dry-run)' : ''
            ));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failure while deleting Fair3R datastores: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
