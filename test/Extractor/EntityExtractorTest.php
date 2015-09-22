<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Extractor;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Hydrator\ObjectProperty;
use ZF\Hal\Extractor\EntityExtractor;
use ZFTest\Hal\Plugin\TestAsset;

/**
 * @subpackage UnitTest
 */
class EntityExtractorTest extends TestCase
{
    public function testExtractGivenEntityWithAssociateHydratorShouldExtractData()
    {
        $hydrator = new ObjectProperty();

        $entity = new TestAsset\Entity('foo', 'Foo Bar');
        $entityHydratorManager = $this->getMockBuilder('ZF\Hal\EntityHydratorManager')
            ->disableOriginalConstructor()
            ->getMock();
        $entityHydratorManager
            ->method('getHydratorForEntity')
            ->with($entity)
            ->will($this->returnValue($hydrator));

        $extractor = new EntityExtractor($entityHydratorManager);

        $this->assertSame($extractor->extract($entity), $hydrator->extract($entity));
    }

    public function testExtractGivenEntityWithoutAssociateHydratorShouldExtractPublicProperties()
    {
        $entity = new TestAsset\Entity('foo', 'Foo Bar');
        $entityHydratorManager = $this->getMockBuilder('ZF\Hal\EntityHydratorManager')
            ->disableOriginalConstructor()
            ->getMock();
        $entityHydratorManager
            ->method('getHydratorForEntity')
            ->with($entity)
            ->will($this->returnValue(null));

        $extractor = new EntityExtractor($entityHydratorManager);
        $data = $extractor->extract($entity);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('doNotExportMe', $data);
    }

    public function testExtractTwiceGivenSameEntityShouldProcessExtractionOnceAndReturnSameData()
    {
        $entity = new TestAsset\Entity('foo', 'Foo Bar');
        $entityHydratorManager = $this->getMockBuilder('ZF\Hal\EntityHydratorManager')
            ->disableOriginalConstructor()
            ->getMock();
        $entityHydratorManager
            ->expects($this->once())
            ->method('getHydratorForEntity')
            ->with($entity)
            ->will($this->returnValue(null));

        $extractor = new EntityExtractor($entityHydratorManager);

        $data1 = $extractor->extract($entity);
        $data2 = $extractor->extract($entity);

        $this->assertSame($data1, $data2);
    }
}
