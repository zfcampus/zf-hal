<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Hydrator\HydratorPluginManager;
use Zend\ServiceManager\ServiceManager;
use ZF\Hal\EntityHydratorManager;
use ZF\Hal\Metadata\MetadataMap;
use ZFTest\Hal\Plugin\TestAsset;

/**
 * @subpackage UnitTest
 */
class EntityHydratorManagerTest extends TestCase
{
    public function testAddHydratorGivenEntityClassAndHydratorInstanceShouldAssociateThem()
    {
        $entity        = new TestAsset\Entity('foo', 'Foo Bar');
        $hydratorClass = TestAsset\DummyHydrator::class;
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
        $hydratorClass = TestAsset\DummyHydrator::class;

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
        $metadataMap           = new MetadataMap();
        $metadataMap->setHydratorManager(new HydratorPluginManager(new ServiceManager()));

        $hydratorPluginManager = new HydratorPluginManager(new ServiceManager());
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadataMap);

        $entityHydratorManager->addHydrator('stdClass', TestAsset\DummyHydrator::class);

        $this->assertInstanceOf(
            TestAsset\DummyHydrator::class,
            $entityHydratorManager->getHydratorForEntity(new \stdClass)
        );
    }

    public function testGetHydratorForEntityGivenEntityDefinedInMetadataMapShouldReturnDefaultHydrator()
    {
        $entity        = new TestAsset\Entity('foo', 'Foo Bar');
        $hydratorClass = TestAsset\DummyHydrator::class;

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
        $entity = new TestAsset\Entity('foo', 'Foo Bar');
        $defaultHydrator = new TestAsset\DummyHydrator();

        $metadataMap           = new MetadataMap();
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
