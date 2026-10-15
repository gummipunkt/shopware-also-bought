<?php declare(strict_types=1);

namespace Wltr\AlsoBought;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Wltr\AlsoBought\Service\AlsoBoughtIndexer;

class WltrAlsoBought extends Plugin
{
    /**
     * Fill the table right away, so recommendations do not wait for the first nightly run.
     * The plugin's own services are not in the container yet during activation – build the indexer from core services.
     */
    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);

        (new AlsoBoughtIndexer(
            $this->container->get(Connection::class),
            $this->container->get(SystemConfigService::class),
        ))->rebuild();
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->container->get(Connection::class)->executeStatement('DROP TABLE IF EXISTS `wltr_also_bought`');
    }
}
