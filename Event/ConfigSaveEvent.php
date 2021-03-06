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

namespace MauticPlugin\IntegrationsBundle\Event;

use Mautic\PluginBundle\Entity\Integration;
use Symfony\Component\EventDispatcher\Event;

class ConfigSaveEvent extends Event
{
    /**
     * @var Integration
     */
    private $integrationConfiguration;

    /**
     * ConfigSaveEvent constructor.
     *
     * @param Integration $integrationConfiguration
     */
    public function __construct(Integration $integrationConfiguration)
    {
        $this->integrationConfiguration = $integrationConfiguration;
    }

    /**
     * @return Integration
     */
    public function getIntegrationConfiguration(): Integration
    {
        return $this->integrationConfiguration;
    }

    /**
     * @return string
     */
    public function getIntegration(): string
    {
        return $this->integrationConfiguration->getName();
    }
}
