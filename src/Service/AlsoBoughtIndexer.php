<?php declare(strict_types=1);

namespace Wltr\AlsoBought\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Rebuilds wltr_also_bought from the order history.
 *
 * Score = number of (non-cancelled) orders that contained both products. Variants are counted for their main
 * product, so "bought together" is independent of size/colour. Only the best `storedPerProduct` candidates are
 * kept per product; the storefront loader skips inactive or unavailable ones at runtime.
 */
class AlsoBoughtIndexer
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfig,
    ) {
    }

    /**
     * @return int number of stored product pairs
     */
    public function rebuild(): int
    {
        $monthsBack = max(0, $this->systemConfig->getInt('WltrAlsoBought.config.monthsBack') ?: 0);
        $minOrders = max(1, $this->systemConfig->getInt('WltrAlsoBought.config.minOrders') ?: 1);
        $perProduct = max(1, $this->systemConfig->getInt('WltrAlsoBought.config.storedPerProduct') ?: 20);

        $since = $monthsBack > 0
            ? (new \DateTimeImmutable())->modify(\sprintf('-%d months', $monthsBack))->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            : null;

        $rows = $this->connection->fetchAllAssociative(
            'WITH items AS (
                SELECT DISTINCT oli.order_id, COALESCE(p.parent_id, p.id) AS main_id
                FROM order_line_item oli
                INNER JOIN `order` o ON o.id = oli.order_id AND o.version_id = oli.order_version_id
                INNER JOIN product p ON p.id = oli.product_id AND p.version_id = :live
                LEFT JOIN state_machine_state s ON s.id = o.state_id
                WHERE oli.version_id = :live
                  AND o.version_id = :live
                  AND oli.type = :productType
                  AND oli.product_id IS NOT NULL
                  AND (s.technical_name IS NULL OR s.technical_name <> :cancelled)
                  AND (:since IS NULL OR o.order_date_time >= :since)
            ),
            pairs AS (
                SELECT a.main_id AS product_id, b.main_id AS related_product_id, COUNT(*) AS score
                FROM items a
                INNER JOIN items b ON b.order_id = a.order_id AND b.main_id <> a.main_id
                GROUP BY a.main_id, b.main_id
                HAVING score >= :minOrders
            ),
            ranked AS (
                SELECT product_id, related_product_id, score,
                       ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY score DESC, related_product_id) AS rn
                FROM pairs
            )
            SELECT product_id, related_product_id, score FROM ranked WHERE rn <= :perProduct',
            [
                'live' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'productType' => 'product',
                'cancelled' => 'cancelled',
                'since' => $since,
                'minOrders' => $minOrders,
                'perProduct' => $perProduct,
            ]
        );

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->transactional(function (Connection $connection) use ($rows, $now): void {
            $connection->executeStatement('DELETE FROM `wltr_also_bought`');

            foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
                $placeholders = [];
                $params = [];
                foreach ($chunk as $row) {
                    $placeholders[] = '(?, ?, ?, ?)';
                    array_push($params, $row['product_id'], $row['related_product_id'], (int) $row['score'], $now);
                }
                $connection->executeStatement(
                    'INSERT INTO `wltr_also_bought` (product_id, related_product_id, score, created_at) VALUES '
                    . implode(', ', $placeholders),
                    $params
                );
            }
        });

        return \count($rows);
    }
}
