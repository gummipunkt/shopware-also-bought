<?php declare(strict_types=1);

namespace Wltr\AlsoBought\Subscriber;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Wltr\AlsoBought\Service\AlsoBoughtLoader;

/**
 * Adds the extension "wltrAlsoBought" (ProductCollection) to the product page and the cart page.
 */
class StorefrontSubscriber implements EventSubscriberInterface
{
    public const EXTENSION = 'wltrAlsoBought';

    public function __construct(
        private readonly AlsoBoughtLoader $loader,
        private readonly SystemConfigService $systemConfig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPage',
            CheckoutCartPageLoadedEvent::class => 'onCartPage',
        ];
    }

    public function onProductPage(ProductPageLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        if (!$this->systemConfig->getBool('WltrAlsoBought.config.showOnProductPage', $salesChannelId)) {
            return;
        }

        $product = $event->getPage()->getProduct();
        $mainId = $product->getParentId() ?? $product->getId();

        $products = $this->loader->load([$mainId], [$product->getId()], $this->maxProducts($salesChannelId), $event->getSalesChannelContext());

        if ($products->count() >= $this->minProducts($salesChannelId)) {
            $event->getPage()->addExtension(self::EXTENSION, $products);
        }
    }

    public function onCartPage(CheckoutCartPageLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        if (!$this->systemConfig->getBool('WltrAlsoBought.config.showInCart', $salesChannelId)) {
            return;
        }

        $cartProductIds = [];
        foreach ($event->getPage()->getCart()->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            if ($lineItem->getReferencedId() !== null) {
                $cartProductIds[] = $lineItem->getReferencedId();
            }
            $parentId = $lineItem->getPayloadValue('parentId');
            if (\is_string($parentId) && $parentId !== '') {
                $cartProductIds[] = $parentId;
            }
        }

        if ($cartProductIds === []) {
            return;
        }

        $products = $this->loader->load($cartProductIds, $cartProductIds, $this->maxProducts($salesChannelId), $event->getSalesChannelContext());

        if ($products->count() >= $this->minProducts($salesChannelId)) {
            $event->getPage()->addExtension(self::EXTENSION, $products);
        }
    }

    private function maxProducts(string $salesChannelId): int
    {
        return max(1, $this->systemConfig->getInt('WltrAlsoBought.config.maxProducts', $salesChannelId) ?: 4);
    }

    private function minProducts(string $salesChannelId): int
    {
        return max(1, $this->systemConfig->getInt('WltrAlsoBought.config.minProducts', $salesChannelId) ?: 1);
    }
}
