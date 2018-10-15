<?php

/*
 * @copyright   2018 Mautic Inc. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://www.mautic.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\IntegrationsBundle\Sync\SyncProcess\Direction\Internal;


use MauticPlugin\IntegrationsBundle\Sync\DAO\Mapping\FieldMappingDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Mapping\MappingManualDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Order\FieldDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Mapping\ObjectMappingDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Order\ObjectChangeDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\InformationChangeRequestDAO;
use MauticPlugin\IntegrationsBundle\Sync\Exception\ConflictUnresolvedException;
use MauticPlugin\IntegrationsBundle\Sync\Exception\FieldNotFoundException;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Report\ObjectDAO as ReportObjectDAO;
use MauticPlugin\IntegrationsBundle\Sync\Exception\ObjectNotFoundException;
use MauticPlugin\IntegrationsBundle\Sync\Logger\DebugLogger;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\MauticSyncDataExchange;
use MauticPlugin\IntegrationsBundle\Sync\SyncJudge\SyncJudgeInterface;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Report\ReportDAO;

class ObjectChangeGenerator
{
    /**
     * @var SyncJudgeInterface
     */
    private $syncJudge;

    /**
     * @var ReportDAO
     */
    private $syncReport;

    /**
     * @var MappingManualDAO
     */
    private $mappingManual;

    /**
     * @var ReportObjectDAO
     */
    private $internalObject;

    /**
     * @var ReportObjectDAO
     */
    private $integrationObject;

    /**
     * @var ObjectChangeDAO
     */
    private $objectChange;

    /**
     * @var array
     */
    private $judgementModes = [
        SyncJudgeInterface::HARD_EVIDENCE_MODE,
        SyncJudgeInterface::BEST_EVIDENCE_MODE,
        SyncJudgeInterface::FUZZY_EVIDENCE_MODE,
    ];

    /**
     * ObjectChangeGenerator constructor.
     *
     * @param SyncJudgeInterface $syncJudge
     */
    public function __construct(SyncJudgeInterface $syncJudge)
    {
        $this->syncJudge = $syncJudge;
    }

    /**
     * @param ReportDAO        $syncReport
     * @param MappingManualDAO $mappingManual
     * @param ObjectMappingDAO $objectMapping
     * @param ReportObjectDAO  $internalObject
     * @param ReportObjectDAO  $integrationObject
     *
     * @return ObjectChangeDAO
     * @throws ObjectNotFoundException
     */
    public function getSyncObjectChange(
        ReportDAO $syncReport,
        MappingManualDAO $mappingManual,
        ObjectMappingDAO $objectMapping,
        ReportObjectDAO $internalObject,
        ReportObjectDAO $integrationObject
    ) {
        $this->syncReport        = $syncReport;
        $this->mappingManual     = $mappingManual;
        $this->internalObject    = $internalObject;
        $this->integrationObject = $integrationObject;

        $this->objectChange = new ObjectChangeDAO(
            $this->mappingManual->getIntegration(),
            $internalObject->getObject(),
            $internalObject->getObjectId(),
            $integrationObject->getObject(),
            $integrationObject->getObjectId()
        );

        if ($internalObject->getObjectId()) {
            DebugLogger::log(
                $this->mappingManual->getIntegration(),
                sprintf(
                    "Integration to Mautic; found a match between Mautic's %s:%s object and the integration %s:%s object ",
                    $internalObject->getObject(),
                    (string) $internalObject->getObjectId(),
                    $integrationObject->getObject(),
                    (string) $integrationObject->getObjectId()
                ),
                __CLASS__.':'.__FUNCTION__
            );
        } else {
            DebugLogger::log(
                $this->mappingManual->getIntegration(),
                sprintf(
                    "Integration to Mautic; no match found for %s:%s",
                    $integrationObject->getObject(),
                    (string) $integrationObject->getObjectId()
                ),
                __CLASS__.':'.__FUNCTION__
            );
        }

        /** @var FieldMappingDAO[] $fieldMappings */
        $fieldMappings = $objectMapping->getFieldMappings();
        foreach ($fieldMappings as $fieldMappingDAO) {
            $this->addFieldToObjectChange($fieldMappingDAO);
        }

        // Set the change date/time from the object so that we can update last sync date based on this
        $this->objectChange->setChangeDateTime($integrationObject->getChangeDateTime());

        return $this->objectChange;
    }

    /**
     * @param FieldMappingDAO $fieldMappingDAO
     *
     * @throws ObjectNotFoundException
     */
    private function addFieldToObjectChange(FieldMappingDAO $fieldMappingDAO)
    {
        try {
            $fieldState = $this->integrationObject->getField($fieldMappingDAO->getIntegrationField())->getState();

            $integrationInformationChangeRequest = $this->syncReport->getInformationChangeRequest(
                $this->integrationObject->getObject(),
                $this->integrationObject->getObjectId(),
                $fieldMappingDAO->getIntegrationField()
            );
        } catch (FieldNotFoundException $e) {
            return;
        }

        // If syncing bidirectional, let the sync judge determine what value should be used for the field
        if (ObjectMappingDAO::SYNC_BIDIRECTIONALLY === $fieldMappingDAO->getSyncDirection()) {
            $this->judgeThenAddFieldToObjectChange($fieldMappingDAO, $integrationInformationChangeRequest, $fieldState);

            return;
        }

        // Add the value to the field based on the field state
        $this->objectChange->addField(
            new FieldDAO($fieldMappingDAO->getInternalField(), $integrationInformationChangeRequest->getNewValue()),
            $fieldState
        );

        /**
         * Below here is just debug logging
         */

        // ObjectMappingDAO::SYNC_TO_MAUTIC
        if (ObjectMappingDAO::SYNC_TO_MAUTIC === $fieldMappingDAO->getSyncDirection()) {
            DebugLogger::log(
                $this->mappingManual->getIntegration(),
                sprintf(
                    "Integration to Mautic; syncing %s %s with a value of %s",
                    $fieldState,
                    $fieldMappingDAO->getInternalField(),
                    var_export($integrationInformationChangeRequest->getNewValue()->getOriginalValue(), true)
                ),
                __CLASS__.':'.__FUNCTION__
            );

            return;
        }

        // ObjectMappingDAO::SYNC_TO_INTEGRATION:
        DebugLogger::log(
            $this->mappingManual->getIntegration(),
            sprintf(
                "Integration to Mautic; the %s object's %s field %s was added to the list of required fields because it's configured to sync to the integration",
                $this->internalObject->getObject(),
                $fieldState,
                $fieldMappingDAO->getInternalField()
            ),
            __CLASS__.':'.__FUNCTION__
        );
    }

    /**
     * @param FieldMappingDAO             $fieldMappingDAO
     * @param InformationChangeRequestDAO $integrationInformationChangeRequest
     * @param string                      $fieldState
     */
    private function judgeThenAddFieldToObjectChange(
        FieldMappingDAO $fieldMappingDAO,
        InformationChangeRequestDAO $integrationInformationChangeRequest,
        string $fieldState
    ) {
        try {
            $internalField = $this->internalObject->getField($fieldMappingDAO->getInternalField());
        } catch (FieldNotFoundException $exception) {
            $internalField = null;
        }

        if (!$internalField) {
            $this->objectChange->addField(
                new FieldDAO(
                    $fieldMappingDAO->getInternalField(),
                    $integrationInformationChangeRequest->getNewValue()
                ),
                $fieldState
            );

            DebugLogger::log(
                $this->mappingManual->getIntegration(),
                sprintf(
                    "Integration to Mautic; the sync is bidirectional but no conflicts were found so syncing the %s object's %s field %s with a value of %s",
                    $this->internalObject->getObject(),
                    $fieldState,
                    $fieldMappingDAO->getInternalField(),
                    var_export($integrationInformationChangeRequest->getNewValue()->getOriginalValue(), true)
                ),
                __CLASS__.':'.__FUNCTION__
            );

            return;
        }

        $internalInformationChangeRequest = new InformationChangeRequestDAO(
            MauticSyncDataExchange::NAME,
            $this->internalObject->getObject(),
            $this->internalObject->getObjectId(),
            $internalField->getName(),
            $internalField->getValue()
        );
        $internalInformationChangeRequest->setPossibleChangeDateTime($this->internalObject->getChangeDateTime());
        $internalInformationChangeRequest->setCertainChangeDateTime($internalField->getChangeDateTime());

        // There is a conflict so let the judge determine which value comes out on top
        foreach ($this->judgementModes as $judgeMode) {
            try {
                $this->makeJudgement(
                    $judgeMode,
                    $fieldMappingDAO,
                    $integrationInformationChangeRequest,
                    $internalInformationChangeRequest,
                    $fieldState
                );

                break;
            } catch (ConflictUnresolvedException $exception) {
                DebugLogger::log(
                    $this->mappingManual->getIntegration(),
                    sprintf(
                        "Integration to Mautic; no winner was determined using the %s judging mode for object %s field %s",
                        $judgeMode,
                        $this->internalObject->getObject(),
                        $fieldMappingDAO->getInternalField()
                    ),
                    __CLASS__.':'.__FUNCTION__
                );
            }
        }
    }

    /**
     * @param string                      $judgeMode
     * @param FieldMappingDAO             $fieldMappingDAO
     * @param InformationChangeRequestDAO $integrationInformationChangeRequest
     * @param InformationChangeRequestDAO $internalInformationChangeRequest
     * @param string                      $fieldState
     *
     * @throws ConflictUnresolvedException
     */
    private function makeJudgement(
        string $judgeMode,
        FieldMappingDAO $fieldMappingDAO,
        InformationChangeRequestDAO $integrationInformationChangeRequest,
        InformationChangeRequestDAO $internalInformationChangeRequest,
        string $fieldState
    ) {
        $winningChangeRequest = $this->syncJudge->adjudicate(
            $judgeMode,
            $internalInformationChangeRequest,
            $integrationInformationChangeRequest
        );

        $this->objectChange->addField(
            new FieldDAO($fieldMappingDAO->getInternalField(), $winningChangeRequest->getNewValue()),
            $fieldState
        );

        DebugLogger::log(
            $this->mappingManual->getIntegration(),
            sprintf(
                "Integration to Mautic; sync judge determined to sync %s to the %s object's %s field %s with a value of %s using the %s judging mode",
                $winningChangeRequest->getIntegration(),
                $winningChangeRequest->getObject(),
                $fieldState,
                $fieldMappingDAO->getInternalField(),
                var_export($winningChangeRequest->getNewValue()->getOriginalValue(), true),
                $judgeMode
            ),
            __CLASS__.':'.__FUNCTION__
        );
    }
}