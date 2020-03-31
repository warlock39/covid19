<?php

namespace App\Command;

use App\DataSource\Actualizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ActualizeCommand extends Command
{
    private Actualizer $actualizer;

    public function __construct(Actualizer $actualizer)
    {
        $this->actualizer = $actualizer;
        parent::__construct('app:actualize');
    }
    protected function configure(): void
    {
        $argDesc = 'DataSource name. ';
        $argDesc .= implode(', ', Actualizer::SUPPORTED_DATASOURCES);
        $argDesc .= '. When no datasource specified. Actualization of all of them are made';
        $this
            ->setDescription('Actualize data to actual state for different datasources')
            ->addArgument('dataSource', InputArgument::OPTIONAL, $argDesc)
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Backfill concrete date. Date is specified in "Y-m-d"')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dataSource = $input->getArgument('dataSource');

        $io = new SymfonyStyle($input, $output);

        if (empty($dataSource)) {
            $io->title('Actualization of all dataSources');
            $this->actualizer->actualize($input->getOption('date'));
        } else {
            $io->title(sprintf('Actualization of "%s" dataSource', $dataSource));
            $this->actualizer->actualizeDataSource($dataSource, $input->getOption('date'));
        }

        $io->success('Actualization completed');

        return 0;
    }

}
