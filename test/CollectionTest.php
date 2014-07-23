<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal;

use ZF\Hal\Collection;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkCollection;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;

class CollectionTest extends TestCase
{
    public function invalidCollections()
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
            'stdclass'   => array(new stdClass),
        );
    }

    /**
     * @dataProvider invalidCollections
     */
    public function testConstructorRaisesExceptionForNonTraversableCollection($collection)
    {
        $this->setExpectedException('ZF\Hal\Exception\InvalidCollectionException');
        $hal = new Collection($collection, 'collection/route', 'item/route');
    }

    public function testPropertiesAreAccessibleFollowingConstruction()
    {
        $hal = new Collection(array(), 'item/route', array('version' => 1), array('query' => 'format=json'));
        $this->assertEquals(array(), $hal->getCollection());
        $this->assertEquals('item/route', $hal->getEntityRoute());
        $this->assertEquals(array('version' => 1), $hal->getEntityRouteParams());
        $this->assertEquals(array('query' => 'format=json'), $hal->getEntityRouteOptions());
    }

    public function testDefaultPageIsOne()
    {
        $hal = new Collection(array(), 'item/route');
        $this->assertEquals(1, $hal->getPage());
    }

    public function testPageIsMutable()
    {
        $hal = new Collection(array(), 'item/route');
        $hal->setPage(5);
        $this->assertEquals(5, $hal->getPage());
    }

    public function testDefaultPageSizeIsThirty()
    {
        $hal = new Collection(array(), 'item/route');
        $this->assertEquals(30, $hal->getPageSize());
    }

    public function testPageSizeIsMutable()
    {
        $hal = new Collection(array(), 'item/route');
        $hal->setPageSize(3);
        $this->assertEquals(3, $hal->getPageSize());
    }

    public function testPageSizeAllowsNegativeOneAsValue()
    {
        $hal = new Collection(array(), 'item/route');
        $hal->setPageSize(-1);
        $this->assertEquals(-1, $hal->getPageSize());
    }

    public function testDefaultCollectionNameIsItems()
    {
        $hal = new Collection(array(), 'item/route');
        $this->assertEquals('items', $hal->getCollectionName());
    }

    public function testCollectionNameIsMutable()
    {
        $hal = new Collection(array(), 'item/route');
        $hal->setCollectionName('records');
        $this->assertEquals('records', $hal->getCollectionName());
    }

    public function testDefaultAttributesAreEmpty()
    {
        $hal = new Collection(array(), 'item/route');
        $this->assertEquals(array(), $hal->getAttributes());
    }

    public function testAttributesAreMutable()
    {
        $hal = new Collection(array(), 'item/route');
        $attributes = array(
            'count' => 1376,
            'order' => 'desc',
        );
        $hal->setAttributes($attributes);
        $this->assertEquals($attributes, $hal->getAttributes());
    }

    public function testComposesLinkCollectionByDefault()
    {
        $hal = new Collection(array(), 'item/route');
        $this->assertInstanceOf('ZF\Hal\Link\LinkCollection', $hal->getLinks());
    }

    public function testLinkCollectionMayBeInjected()
    {
        $hal   = new Collection(array(), 'item/route');
        $links = new LinkCollection();
        $hal->setLinks($links);
        $this->assertSame($links, $hal->getLinks());
    }

    public function testAllowsSettingAdditionalEntityLinks()
    {
        $links = new LinkCollection();
        $links->add(new Link('describedby'));
        $links->add(new Link('orders'));
        $hal   = new Collection(array(), 'item/route');
        $hal->setEntityLinks($links);
        $this->assertSame($links, $hal->getEntityLinks());
    }
}
