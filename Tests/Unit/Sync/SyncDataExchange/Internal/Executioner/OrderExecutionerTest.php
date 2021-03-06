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

namespace MauticPlugin\IntegrationsBundle\Tests\Unit\Sync\SyncDataExchange\Internal\Executioner;

use MauticPlugin\IntegrationsBundle\Event\InternalObjectCreateEvent;
use MauticPlugin\IntegrationsBundle\Event\InternalObjectUpdateEvent;
use MauticPlugin\IntegrationsBundle\IntegrationEvents;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Order\ObjectChangeDAO;
use MauticPlugin\IntegrationsBundle\Sync\DAO\Sync\Order\OrderDAO;
use MauticPlugin\IntegrationsBundle\Sync\Helper\MappingHelper;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\Executioner\OrderExecutioner;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\Object\Company;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\Object\Contact;
use MauticPlugin\IntegrationsBundle\Sync\SyncDataExchange\Internal\ObjectProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderExecutionerTest extends \PHPUnit_Framework_TestCase
{
    private const INTEGRATION_NAME = 'Test';

    /**
     * @var MappingHelper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $mappingHelper;

    /**
     * @var EventDispatcherInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $dispatcher;

    /**
     * @var ObjectProvider|\PHPUnit_Framework_MockObject_MockObject
     */
    private $objectProvider;

    /**
     * @var OrderExecutioner
     */
    private $orderExecutioner;

    protected function setup(): void
    {
        $this->mappingHelper    = $this->createMock(MappingHelper::class);
        $this->dispatcher       = $this->createMock(EventDispatcherInterface::class);
        $this->objectProvider   = $this->createMock(ObjectProvider::class);
        $this->orderExecutioner = new OrderExecutioner(
            $this->mappingHelper,
            $this->dispatcher,
            $this->objectProvider
        );
    }

    public function testContactsAreUpdatedAndCreated(): void
    {
        $this->objectProvider->expects($this->exactly(2))
            ->method('getObjectByName')
            ->with(Contact::NAME)
            ->willReturn(new Contact());

        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [
                    IntegrationEvents::INTEGRATION_UPDATE_INTERNAL_OBJECTS,
                    $this->callback(function (InternalObjectUpdateEvent $event) {
                        $this->assertSame(Contact::NAME, $event->getObject()->getName());
                        $this->assertSame([1, 2], $event->getIdentifiedObjectIds());
                        $this->assertCount(2, $event->getUpdateObjects());

                        return true;
                    }),
                ],
                [
                    IntegrationEvents::INTEGRATION_CREATE_INTERNAL_OBJECTS,
                    $this->callback(function (InternalObjectCreateEvent $event) {
                        $this->assertSame(Contact::NAME, $event->getObject()->getName());
                        $this->assertCount(1, $event->getCreateObjects());

                        return true;
                    }),
                ]
            );

        $this->mappingHelper->expects($this->exactly(1))
            ->method('updateObjectMappings');

        $this->mappingHelper->expects($this->exactly(1))
            ->method('saveObjectMappings');

        $this->orderExecutioner->execute($this->getSyncOrder(Contact::NAME));
    }

    public function testCompaniesAreUpdatedAndCreated(): void
    {
        $this->objectProvider->expects($this->exactly(2))
            ->method('getObjectByName')
            ->with(Company::NAME)
            ->willReturn(new Company());

        $this->dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [
                    IntegrationEvents::INTEGRATION_UPDATE_INTERNAL_OBJECTS,
                    $this->callback(function (InternalObjectUpdateEvent $event) {
                        $this->assertSame(Company::NAME, $event->getObject()->getName());
                        $this->assertSame([1, 2], $event->getIdentifiedObjectIds());
                        $this->assertCount(2, $event->getUpdateObjects());

                        return true;
                    }),
                ],
                [
                    IntegrationEvents::INTEGRATION_CREATE_INTERNAL_OBJECTS,
                    $this->callback(function (InternalObjectCreateEvent $event) {
                        $this->assertSame(Company::NAME, $event->getObject()->getName());
                        $this->assertCount(1, $event->getCreateObjects());

                        return true;
                    }),
                ]
            );

        $this->mappingHelper->expects($this->exactly(1))
            ->method('updateObjectMappings');

        $this->mappingHelper->expects($this->exactly(1))
            ->method('saveObjectMappings');

        $syncOrder = $this->getSyncOrder(Company::NAME);
        $this->orderExecutioner->execute($syncOrder);
    }

    public function testMixedObjectsAreUpdatedAndCreated(): void
    {
        $this->objectProvider->expects($this->exactly(4))
            ->method('getObjectByName')
            ->withConsecutive(
                [Contact::NAME],
                [Company::NAME],
                [Contact::NAME],
                [Company::NAME]
            )
            ->willReturnOnConsecutiveCalls(
                new Contact(),
                new Company(),
                new Contact(),
                new Company()
            );

        $this->dispatcher->expects($this->exactly(4))
            ->method('dispatch')
            ->withConsecutive(
                [
                    IntegrationEvents::INTEGRATION_UPDATE_INTERNAL_OBJECTS,
                    $this->callback(function (InternalObjectUpdateEvent $event) {
                        $this->assertSame(Contact::NAME, $event->getObject()->getName());

                        return true;
                    }),
                ],
                [
                    IntegrationEvents::INTEGRATION_UPDATE_INTERNAL_OBJECTS,
                    $this->callback(function (InternalObjectUpdateEvent $event) {
                        $this->assertSame(Company::NAME, $event->getObject()->getName());

                        return true;
                    }),
                ],
                [
                    IntegrationEvents::INTEGRATION_CREATE_INTERNAL_OBJECTS,
                    $this->callback(function (InternalObjectCreateEvent $event) {
                        $this->assertSame(Contact::NAME, $event->getObject()->getName());

                        return true;
                    }),
                ],
                [
                    IntegrationEvents::INTEGRATION_CREATE_INTERNAL_OBJECTS,
                    $this->callback(function (InternalObjectCreateEvent $event) {
                        $this->assertSame(Company::NAME, $event->getObject()->getName());

                        return true;
                    }),
                ]
            );

        $this->mappingHelper->expects($this->exactly(2))
            ->method('updateObjectMappings');

        $this->mappingHelper->expects($this->exactly(2))
            ->method('saveObjectMappings');

        // Merge companies and contacts for the test
        $syncOrder        = $this->getSyncOrder(Contact::NAME);
        $companySyncOrder = $this->getSyncOrder(Company::NAME);
        foreach ($companySyncOrder->getChangedObjectsByObjectType(Company::NAME) as $objectChange) {
            $syncOrder->addObjectChange($objectChange);
        }

        $this->orderExecutioner->execute($syncOrder);
    }

    /**
     * @param string $objectName
     *
     * @return OrderDAO
     *
     * @throws \Exception
     */
    private function getSyncOrder(string $objectName): OrderDAO
    {
        $syncOrder = new OrderDAO(new \DateTimeImmutable(), false, self::INTEGRATION_NAME);

        // Two updates
        $syncOrder->addObjectChange(new ObjectChangeDAO(self::INTEGRATION_NAME, $objectName, 1, $objectName, 1));
        $syncOrder->addObjectChange(new ObjectChangeDAO(self::INTEGRATION_NAME, $objectName, 2, $objectName, 2));

        // One create
        $syncOrder->addObjectChange(new ObjectChangeDAO(self::INTEGRATION_NAME, $objectName, null, $objectName, 3));

        return $syncOrder;
    }
}
