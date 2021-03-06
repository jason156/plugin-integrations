<?php

declare(strict_types=1);

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Sync\Notification;

use MauticPlugin\IntegrationsBundle\Exception\IntegrationNotFoundException;
use MauticPlugin\IntegrationsBundle\Helper\ConfigIntegrationsHelper;
use MauticPlugin\IntegrationsBundle\Helper\SyncIntegrationsHelper;
use MauticPlugin\IntegrationsBundle\Integration\Interfaces\ConfigFormSyncInterface;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Order\NotificationDAO;
use MauticPlugin\IntegrationsBundle\Sync\Exception\HandlerNotSupportedException;
use MauticPlugin\IntegrationsBundle\Sync\Notification\Handler\HandlerContainer;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange;

class Notifier
{
    /**
     * @var HandlerContainer
     */
    private $handlerContainer;

    /**
     * @var SyncIntegrationsHelper
     */
    private $syncIntegrationsHelper;

    /**
     * @var ConfigIntegrationsHelper
     */
    private $configIntegrationsHelper;

    /**
     * @param HandlerContainer         $handlerContainer
     * @param SyncIntegrationsHelper   $syncIntegrationsHelper
     * @param ConfigIntegrationsHelper $configIntegrationsHelper
     */
    public function __construct(
        HandlerContainer $handlerContainer,
        SyncIntegrationsHelper $syncIntegrationsHelper,
        ConfigIntegrationsHelper $configIntegrationsHelper
    ) {
        $this->handlerContainer         = $handlerContainer;
        $this->syncIntegrationsHelper   = $syncIntegrationsHelper;
        $this->configIntegrationsHelper = $configIntegrationsHelper;
    }

    /**
     * @param NotificationDAO[] $notifications
     * @param string            $integrationHandler
     *
     * @throws HandlerNotSupportedException
     * @throws IntegrationNotFoundException
     */
    public function noteMauticSyncIssue(array $notifications, $integrationHandler = MauticSyncDataExchange::NAME): void
    {
        foreach ($notifications as $notification) {
            $handler = $this->handlerContainer->getHandler($integrationHandler, $notification->getMauticObject());

            $integrationDisplayName = $this->syncIntegrationsHelper->getIntegration($notification->getIntegration())->getDisplayName();
            $objectDisplayName      = $this->getObjectDisplayName($notification->getIntegration(), $notification->getIntegrationObject());

            $handler->writeEntry($notification, $integrationDisplayName, $objectDisplayName);
        }
    }

    /**
     * Finalizes notifications such as pushing summary entries to the user notifications.
     */
    public function finalizeNotifications(): void
    {
        foreach ($this->handlerContainer->getHandlers() as $handler) {
            $handler->finalize();
        }
    }

    /**
     * @param string $integration
     * @param string $object
     *
     * @return string
     */
    private function getObjectDisplayName(string $integration, string $object)
    {
        try {
            $configIntegration = $this->configIntegrationsHelper->getIntegration($integration);
        } catch (IntegrationNotFoundException $exception) {
            return ucfirst($object);
        }

        if (!$configIntegration instanceof ConfigFormSyncInterface) {
            return ucfirst($object);
        }

        $objects = $configIntegration->getSyncConfigObjects();

        if (!isset($objects[$object])) {
            return ucfirst($object);
        }

        return $objects[$object];
    }
}
