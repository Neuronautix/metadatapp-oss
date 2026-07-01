<?php

declare(strict_types=1);

namespace App\Command\Impc;

use App\Application\Service\Impc\SolrImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'impc:import:solr',
    description: 'Import data from IMPC Solr API',
)]
class ImportSolrCommand extends Command
{
    public function __construct(
        private SolrImporter $solrImporter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('IMPC Solr Import');

        $this->solrImporter->import();

        $io->success('Import completed');

        return Command::SUCCESS;
    }
}
