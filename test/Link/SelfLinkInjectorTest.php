<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Link;

use Prophecy\Argument;
use ZF\Hal\Collection;
use ZF\Hal\Entity;
use ZF\Hal\Link\LinkCollection;
use ZF\Hal\Link\SelfLinkInjector;
use PHPUnit_Framework_TestCase as TestCase;

class SelfLinkInjectorTest extends TestCase
{
    public function testInjectSelfLinkAlreadyAddedShouldBePrevented()
    {
        $linkCollection = $this->prophesize(LinkCollection::class);

        $linkCollection->has('self')->willReturn(true);
        $linkCollection->add(Argument::any())->shouldNotBeCalled();

        $resource = new Entity([]);
        $resource->setLinks($linkCollection->reveal());

        $injector = new SelfLinkInjector();
        $injector->injectSelfLink($resource, 'foo');
    }

    public function testInjectEntitySelfLinkShouldAddSelfLinkToLinkCollection()
    {
        $linkCollection = new LinkCollection();

        $resource = new Entity([]);
        $resource->setLinks($linkCollection);

        $injector = new SelfLinkInjector();
        $injector->injectSelfLink($resource, 'foo');

        $this->assertTrue($linkCollection->has('self'));
    }

    public function testInjectCollectionSelfLinkShouldAddSelfLinkToLinkCollection()
    {
        $linkCollection = new LinkCollection();

        $resource = new Collection([]);
        $resource->setLinks($linkCollection);

        $injector = new SelfLinkInjector();
        $injector->injectSelfLink($resource, 'foo');

        $this->assertTrue($linkCollection->has('self'));
    }

    public function testInjectEntitySelfLinkWithIdentifierShouldAddSelfLinkWithIdentifierRouteParam()
    {
        $routeIdentifier = 'id';

        $linkCollection = new LinkCollection();

        $resource = new Entity([], 123);
        $resource->setLinks($linkCollection);

        $injector = new SelfLinkInjector();
        $injector->injectSelfLink($resource, 'foo', $routeIdentifier);

        $this->assertTrue($linkCollection->has('self'));

        $selfLink = $linkCollection->get('self');
        $linkRouteParams = $selfLink->getRouteParams();

        $this->assertArrayHasKey($routeIdentifier, $linkRouteParams);
    }
}
