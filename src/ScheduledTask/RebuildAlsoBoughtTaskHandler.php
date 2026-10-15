<?php declare(strict_types=1);

namespace Wltr\AlsoBought\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Wltr\AlsoBought\Service\AlsoBoughtIndexer;

#[AsMessageHandler(handles: RebuildAlsoBoughtTask::class)]
class RebuildAlsoBoughtTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly AlsoBoughtIndexer $indexer,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->indexer->rebuild();
    }
}
