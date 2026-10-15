<?php declare(strict_types=1);

namespace Wltr\AlsoBought\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class RebuildAlsoBoughtTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'wltr.also_bought.rebuild';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // daily
    }
}
