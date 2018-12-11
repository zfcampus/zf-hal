<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2017 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal;

use PHPUnit\Framework\TestCase;
use stdClass;
use Zend\Hydrator\HydratorPluginManager;
use Zend\Hydrator\HydratorPluginManagerInterface;
use Zend\ServiceManager\ServiceManager;
use ZF\Hal\EntityHydratorManager;
use ZF\Hal\Metadata\MetadataMap;
use ZFTest\Hal\Plugin\TestAsset;

/**
 * @subpackage UnitTest
 */
class EntityHydratorManagerTest extends TestCase
{
    /** @var string */
    private $hydratorClass;

    public function setUp()
    {
        $this->hydratorClass = interface_exists(HydratorPluginManagerInterface::class)
            ? TestAsset\DummyV3Hydrator::class
            : TestAsset\DummyHydrator::class;
    }

    public function testAddHydratorGivenEntityClassAndHydratorInstanceShouldAssociateThem()
    {
        $entity        = new TestAsset\Entity('foo', 'Foo Bar');
        $hydratorClass = $this->hydratorClass;
        $hydrator      = new $hydratorClass();

        $metadataMap = new MetadataMap();
        $metadataMap->setHydratorManager(new HydratorPluginManager(new ServiceManager()));

        $hydratorPluginManager = new HydratorPluginManager(new ServiceManager());
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $entityHydratorManager->addHydrator(TestAsset\Entity::class, $hydrator);

        $entityHydrator = $entityHydratorManager->getHydratorForEntity($entity);
        $this->assertInstanceOf($hydratorClass, $entityHydrator);
        $this->assertSame($hydrator, $entityHydrator);
    }

    public function testAddHydratorGivenEntityAndHydratorClassesShouldAssociateThem()
    {
        $entity        = new TestAsset\Entity('foo', 'Foo Bar');
        $hydratorClass = $this->hydratorClass;

        $metadataMap = new MetadataMap();
        $metadataMap->setHydratorManager(new HydratorPluginManager(new ServiceManager()));

        $hydratorPluginManager = new HydratorPluginManager(new ServiceManager());
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $entityHydratorManager->addHydrator(TestAsset\Entity::class, $hydratorClass);

        $this->assertInstanceOf(
            $hydratorClass,
            $entityHydratorManager->getHydratorForEntity($entity)
        );
    }

    public function testAddHydratorDoesntFailWithAutoInvokables()
    {
        $metadataMap = new MetadataMap();
        $metadataMap->setHydratorManager(new HydratorPluginManager(new ServiceManager()));

        $hydratorPluginManager = new HydratorPluginManager(new ServiceManager());
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $entityHydratorManager->addHydrator(stdClass::class, $this->hydratorClass);

        $this->assertInstanceOf(
            $this->hydratorClass,
            $entityHydratorManager->getHydratorForEntity(new stdClass())
        );
    }

    public function testGetHydratorForEntityGivenEntityDefinedInMetadataMapShouldReturnDefaultHydrator()
    {
        $entity        = new TestAsset\Entity('foo', 'Foo Bar');
        $hydratorClass = $this->hydratorClass;

        $metadataMap = new MetadataMap([
            TestAsset\Entity::class => [
                'hydrator' => $hydratorClass,
            ],
        ]);

        $metadataMap->setHydratorManager(new HydratorPluginManager(new ServiceManager()));

        $hydratorPluginManager = new HydratorPluginManager(new ServiceManager());
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $this->assertInstanceOf(
            $hydratorClass,
            $entityHydratorManager->getHydratorForEntity($entity)
        );
    }

    public function testGetHydratorForEntityGivenUnkownEntityShouldReturnDefaultHydrator()
    {
        $entity          = new TestAsset\Entity('foo', 'Foo Bar');
        $hydratorClass   = $this->hydratorClass;
        $defaultHydrator = new $hydratorClass();

        $metadataMap = new MetadataMap();
        $metadataMap->setHydratorManager(new HydratorPluginManager(new ServiceManager()));

        $hydratorPluginManager = new HydratorPluginManager(new ServiceManager());
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $entityHydratorManager->setDefaultHydrator($defaultHydrator);

        $entityHydrator = $entityHydratorManager->getHydratorForEntity($entity);

        $this->assertSame($defaultHydrator, $entityHydrator);
    }

    public function testGetHydratorForEntityGivenUnknownEntityAndNoDefaultHydratorDefinedShouldReturnFalse()
    {
        $entity = new TestAsset\Entity('foo', 'Foo Bar');

        $metadataMap           = new MetadataMap();
        $metadataMap->setHydratorManager(new HydratorPluginManager(new ServiceManager()));

        $hydratorPluginManager = new HydratorPluginManager(new ServiceManager());
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $hydrator = $entityHydratorManager->getHydratorForEntity($entity);

        $this->assertFalse($hydrator);
    }
}
