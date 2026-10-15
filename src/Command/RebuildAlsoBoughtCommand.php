<?php declare(strict_types=1);

namespace Wltr\AlsoBought\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wltr\AlsoBought\Service\AlsoBoughtIndexer;

#[AsCommand(name: 'wltr:also-bought:rebuild', description: 'Recalculates "customers also bought" from the order history')]
class RebuildAlsoBoughtCommand extends Command
{
    public function __construct(private readonly AlsoBoughtIndexer $indexer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->indexer->rebuild();
        $output->writeln(\sprintf('Stored %d product pairs.', $count));

        return self::SUCCESS;
    }
}
