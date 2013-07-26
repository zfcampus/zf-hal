<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZFTest\Hal;

use ZF\Hal\HalResource;
use ZF\Hal\LinkCollection;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;

class HalResourceTest extends TestCase
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
        $hal = new HalResource($resource, 'id');
    }

    public function testPropertiesAreAccessibleAfterConstruction()
    {
        $resource = new stdClass;
        $hal      = new HalResource($resource, 'id');
        $this->assertSame($resource, $hal->resource);
        $this->assertEquals('id', $hal->id);
    }

    public function testComposesLinkCollectionByDefault()
    {
        $resource = new stdClass;
        $hal      = new HalResource($resource, 'id', 'route', array('foo' => 'bar'));
        $this->assertInstanceOf('ZF\Hal\LinkCollection', $hal->getLinks());
    }

    public function testLinkCollectionMayBeInjected()
    {
        $resource = new stdClass;
        $hal      = new HalResource($resource, 'id', 'route', array('foo' => 'bar'));
        $links    = new LinkCollection();
        $hal->setLinks($links);
        $this->assertSame($links, $hal->getLinks());
    }
}
