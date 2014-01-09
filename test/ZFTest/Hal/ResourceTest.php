<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2013 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal;

use ZF\Hal\Resource;
use ZF\Hal\Link\LinkCollection;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;

class ResourceTest extends TestCase
{
    public function invalidResources()
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
     * @dataProvider invalidResources
     */
    public function testConstructorRaisesExceptionForNonObjectNonArrayResource($resource)
    {
        $this->setExpectedException('ZF\Hal\Exception\InvalidResourceException');
        $hal = new Resource($resource, 'id');
    }

    public function testPropertiesAreAccessibleAfterConstruction()
    {
        $resource = new stdClass;
        $hal      = new Resource($resource, 'id');
        $this->assertSame($resource, $hal->resource);
        $this->assertEquals('id', $hal->id);
    }

    public function testComposesLinkCollectionByDefault()
    {
        $resource = new stdClass;
        $hal      = new Resource($resource, 'id', 'route', array('foo' => 'bar'));
        $this->assertInstanceOf('ZF\Hal\Link\LinkCollection', $hal->getLinks());
    }

    public function testLinkCollectionMayBeInjected()
    {
        $resource = new stdClass;
        $hal      = new Resource($resource, 'id', 'route', array('foo' => 'bar'));
        $links    = new LinkCollection();
        $hal->setLinks($links);
        $this->assertSame($links, $hal->getLinks());
    }

    public function testRetrievingResourceCanReturnByReference()
    {
        $resource = ['foo' => 'bar'];
        $hal      = new Resource($resource, 'id');
        $this->assertEquals($resource, $hal->resource);

        $resource =& $hal->resource;
        $resource['foo'] = 'baz';

        $secondRetrieval =& $hal->resource;
        $this->assertEquals('baz', $secondRetrieval['foo']);
    }
}
