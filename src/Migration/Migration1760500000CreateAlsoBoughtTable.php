<?php declare(strict_types=1);

namespace Wltr\AlsoBought\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Pre-computed co-purchase scores: "orders that contained product_id also contained related_product_id".
 * Filled by Service\AlsoBoughtIndexer (scheduled task / console command), read on every product or cart page.
 */
class Migration1760500000CreateAlsoBoughtTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1760500000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `wltr_also_bought` (
                `product_id` BINARY(16) NOT NULL,
                `related_product_id` BINARY(16) NOT NULL,
                `score` INT UNSIGNED NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`product_id`, `related_product_id`),
                KEY `idx.wltr_also_bought.score` (`product_id`, `score`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
