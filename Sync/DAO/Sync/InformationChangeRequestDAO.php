<?php

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Sync\DAO\Sync;

use MauticPlugin\IntegrationsBundle\Sync\DAO\Value\NormalizedValueDAO;

/**
 * Class InformationChangeRequestDAO
 */
class InformationChangeRequestDAO
{
    /**
     * @var string
     */
    private $integration;

    /**
     * @var string
     */
    private $objectName;

    /**
     * @var mixed
     */
    private $objectId;

    /**
     * @var string
     */
    private $field;

    /**
     * @var NormalizedValueDAO
     */
    private $newValue;

    /**
     * @var \DateTimeInterface|null
     */
    private $possibleChangeDateTime = null;

    /**
     * @var \DateTimeInterface|null
     */
    private $certainChangeDateTime = null;

    /**
     * InformationChangeRequestDAO constructor.
     *
     * @param string             $integration
     * @param string             $objectName
     * @param mixed              $objectId
     * @param string             $field
     * @param NormalizedValueDAO $normalizedValueDAO
     */
    public function __construct($integration, $objectName, $objectId, $field, NormalizedValueDAO $normalizedValueDAO)
    {
        $this->integration = $integration;
        $this->objectName  = $objectName;
        $this->objectId    = $objectId;
        $this->field       = $field;
        $this->newValue    = $normalizedValueDAO;
    }

    /**
     * @return string
     */
    public function getIntegration()
    {
        return $this->integration;
    }

    /**
     * @return mixed
     */
    public function getObjectId()
    {
        return $this->objectId;
    }

    /**
     * @return string
     */
    public function getObject()
    {
        return $this->objectName;
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return NormalizedValueDAO
     */
    public function getNewValue()
    {
        return $this->newValue;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getPossibleChangeDateTime()
    {
        return $this->possibleChangeDateTime;
    }

    /**
     * @param \DateTimeInterface|null $possibleChangeDateTime
     *
     * @return InformationChangeRequestDAO
     */
    public function setPossibleChangeDateTime(\DateTimeInterface $possibleChangeDateTime = null)
    {
        $this->possibleChangeDateTime = $possibleChangeDateTime;

        return $this;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getCertainChangeDateTime()
    {
        return $this->certainChangeDateTime;
    }

    /**
     * @param \DateTimeInterface|null $certainChangeDateTime
     *
     * @return InformationChangeRequestDAO
     */
    public function setCertainChangeDateTime(\DateTimeInterface $certainChangeDateTime = null)
    {
        $this->certainChangeDateTime = $certainChangeDateTime;

        return $this;
    }
}