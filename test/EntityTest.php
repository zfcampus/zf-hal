<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal;

use ZF\Hal\Entity;
use ZF\Hal\Link\LinkCollection;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;

class EntityTest extends TestCase
{
    public function invalidEntities()
    {
        return array(
            'null'       => array(null),
            'true'       => array(true),
            'false'      => array(false),
            'zero-int'   => array(0),
            'int'        => array(1),
            'zero-float' => array(0.0),
            'float'      => array(1.1),
            'string'     => array('string'),
        );
    }

    /**
     * @dataProvider invalidEntities
     */
    public function testConstructorRaisesExceptionForNonObjectNonArrayEntity($entity)
    {
        $this->setExpectedException('ZF\Hal\Exception\InvalidEntityException');
        $hal = new Entity($entity, 'id');
    }

    public function testPropertiesAreAccessibleAfterConstruction()
    {
        $entity = new stdClass;
        $hal    = new Entity($entity, 'id');
        $this->assertSame($entity, $hal->entity);
        $this->assertEquals('id', $hal->id);
    }

    public function testComposesLinkCollectionByDefault()
    {
        $entity = new stdClass;
        $hal    = new Entity($entity, 'id', 'route', array('foo' => 'bar'));
        $this->assertInstanceOf('ZF\Hal\Link\LinkCollection', $hal->getLinks());
    }

    public function testLinkCollectionMayBeInjected()
    {
        $entity = new stdClass;
        $hal    = new Entity($entity, 'id', 'route', array('foo' => 'bar'));
        $links  = new LinkCollection();
        $hal->setLinks($links);
        $this->assertSame($links, $hal->getLinks());
    }

    public function testRetrievingEntityCanReturnByReference()
    {
        $entity = array('foo' => 'bar');
        $hal    = new Entity($entity, 'id');
        $this->assertEquals($entity, $hal->entity);

        $entity =& $hal->entity;
        $entity['foo'] = 'baz';

        $secondRetrieval =& $hal->entity;
        $this->assertEquals('baz', $secondRetrieval['foo']);
    }

    /**
     * @group 39
     */
    public function testConstructorAllowsNullIdentifier()
    {
        $hal = new Entity(array('foo' => 'bar'), null);
        $this->assertNull($hal->id);
    }
}
