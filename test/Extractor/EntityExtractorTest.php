<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2017 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Extractor;

use PHPUnit\Framework\TestCase;
use Zend\Hydrator\ObjectProperty;
use Zend\Hydrator\ObjectPropertyHydrator;
use ZF\Hal\EntityHydratorManager;
use ZF\Hal\Extractor\EntityExtractor;
use ZFTest\Hal\Plugin\TestAsset;

/**
 * @subpackage UnitTest
 */
class EntityExtractorTest extends TestCase
{
    /** @var string */
    private $hydratorClass;

    public function setUp()
    {
        $this->hydratorClass = class_exists(ObjectPropertyHydrator::class)
            ? ObjectPropertyHydrator::class
            : ObjectProperty::class;
    }

    public function testExtractGivenEntityWithAssociateHydratorShouldExtractData()
    {
        $hydrator = new $this->hydratorClass();

        $entity = new TestAsset\Entity('foo', 'Foo Bar');
        $entityHydratorManager = $this->prophesize(EntityHydratorManager::class);
        $entityHydratorManager->getHydratorForEntity($entity)->willReturn($hydrator);

        $extractor = new EntityExtractor($entityHydratorManager->reveal());

        $this->assertSame($extractor->extract($entity), $hydrator->extract($entity));
    }

    public function testExtractGivenEntityWithoutAssociateHydratorShouldExtractPublicProperties()
    {
        $entity = new TestAsset\Entity('foo', 'Foo Bar');
        $entityHydratorManager = $this->prophesize(EntityHydratorManager::class);
        $entityHydratorManager->getHydratorForEntity($entity)->willReturn(null);

        $extractor = new EntityExtractor($entityHydratorManager->reveal());
        $data = $extractor->extract($entity);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('doNotExportMe', $data);
    }

    public function testExtractTwiceGivenSameEntityShouldProcessExtractionOnceAndReturnSameData()
    {
        $entity = new TestAsset\Entity('foo', 'Foo Bar');
        $entityHydratorManager = $this->prophesize(EntityHydratorManager::class);
        $entityHydratorManager->getHydratorForEntity($entity)->willReturn(null)->shouldBeCalledTimes(1);

        $extractor = new EntityExtractor($entityHydratorManager->reveal());

        $data1 = $extractor->extract($entity);
        $data2 = $extractor->extract($entity);

        $this->assertSame($data1, $data2);
    }
}
