<?php declare(strict_types=1);

namespace Wltr\AlsoBought\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Loads the "customers also bought" products for one or more source products, ordered by co-purchase score.
 * Only products that are visible in the sales channel and currently available are returned.
 */
class AlsoBoughtLoader
{
    /**
     * @param SalesChannelRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly SalesChannelRepository $productRepository,
    ) {
    }

    /**
     * @param list<string> $sourceIds product ids (hex) the recommendations are for
     * @param list<string> $excludeIds product ids (hex) that must not be recommended (e.g. already in the cart)
     */
    public function load(array $sourceIds, array $excludeIds, int $limit, SalesChannelContext $context): ProductCollection
    {
        $sourceIds = array_values(array_unique(array_filter($sourceIds)));
        if ($sourceIds === [] || $limit < 1) {
            return new ProductCollection();
        }

        // several sources (cart): sum the scores, best first
        $scores = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(related_product_id)) AS id, SUM(score) AS score
             FROM wltr_also_bought
             WHERE product_id IN (:ids)
             GROUP BY related_product_id
             ORDER BY score DESC',
            ['ids' => Uuid::fromHexToBytesList($sourceIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $exclude = array_flip(array_merge($sourceIds, $excludeIds));
        $candidateIds = array_values(array_filter(array_keys($scores), static fn (string $id) => !isset($exclude[$id])));
        if ($candidateIds === []) {
            return new ProductCollection();
        }

        $criteria = new Criteria($candidateIds);
        $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_ALL));
        $criteria->addFilter(new EqualsFilter('available', true));
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('manufacturer');
        $criteria->setTitle('wltr-also-bought::load');

        /** @var ProductCollection $products */
        $products = $this->productRepository->search($criteria, $context)->getEntities();

        $result = new ProductCollection();
        foreach ($candidateIds as $id) {
            $product = $products->get($id);
            if ($product !== null) {
                $result->add($product);
            }
            if ($result->count() >= $limit) {
                break;
            }
        }

        return $result;
    }
}
