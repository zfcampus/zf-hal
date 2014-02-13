<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Link;

use ZF\Hal\Link\Link;
use PHPUnit_Framework_TestCase as TestCase;

class LinkTest extends TestCase
{
    public function testConstructorTakesLinkRelationName()
    {
        $link = new Link('describedby');
        $this->assertEquals('describedby', $link->getRelation());
    }

    public function testCanSetLinkUrl()
    {
        $url  = 'http://example.com/docs.html';
        $link = new Link('describedby');
        $link->setUrl($url);
        $this->assertEquals($url, $link->getUrl());
    }

    public function testCanSetLinkRoute()
    {
        $route = 'api/docs';
        $link = new Link('describedby');
        $link->setRoute($route);
        $this->assertEquals($route, $link->getRoute());
    }

    public function testCanSetRouteParamsWhenSpecifyingRoute()
    {
        $route  = 'api/docs';
        $params = array('version' => '1.1');
        $link = new Link('describedby');
        $link->setRoute($route, $params);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($params, $link->getRouteParams());
    }

    public function testCanSetRouteOptionsWhenSpecifyingRoute()
    {
        $route   = 'api/docs';
        $options = array('query' => 'version=1.1');
        $link = new Link('describedby');
        $link->setRoute($route, null, $options);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($options, $link->getRouteOptions());
    }

    public function testCanSetRouteParamsSeparately()
    {
        $route  = 'api/docs';
        $params = array('version' => '1.1');
        $link = new Link('describedby');
        $link->setRoute($route);
        $link->setRouteParams($params);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($params, $link->getRouteParams());
    }

    public function testCanSetRouteOptionsSeparately()
    {
        $route   = 'api/docs';
        $options = array('query' => 'version=1.1');
        $link = new Link('describedby');
        $link->setRoute($route);
        $link->setRouteOptions($options);
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($options, $link->getRouteOptions());
    }

    public function testSettingUrlAfterSettingRouteRaisesException()
    {
        $link = new Link('describedby');
        $link->setRoute('api/docs');

        $this->setExpectedException('ZF\ApiProblem\Exception\DomainException');
        $link->setUrl('http://example.com/api/docs.html');
    }

    public function testSettingRouteAfterSettingUrlRaisesException()
    {
        $link = new Link('describedby');
        $link->setUrl('http://example.com/api/docs.html');

        $this->setExpectedException('ZF\ApiProblem\Exception\DomainException');
        $link->setRoute('api/docs');
    }

    public function testIsCompleteReturnsFalseIfNeitherUrlNorRouteIsSet()
    {
        $link = new Link('describedby');
        $this->assertFalse($link->isComplete());
    }

    public function testHasUrlReturnsFalseWhenUrlIsNotSet()
    {
        $link = new Link('describedby');
        $this->assertFalse($link->hasUrl());
    }

    public function testHasUrlReturnsTrueWhenUrlIsSet()
    {
        $link = new Link('describedby');
        $link->setUrl('http://example.com/api/docs.html');
        $this->assertTrue($link->hasUrl());
    }

    public function testIsCompleteReturnsTrueWhenUrlIsSet()
    {
        $link = new Link('describedby');
        $link->setUrl('http://example.com/api/docs.html');
        $this->assertTrue($link->isComplete());
    }

    public function testHasRouteReturnsFalseWhenRouteIsNotSet()
    {
        $link = new Link('describedby');
        $this->assertFalse($link->hasRoute());
    }

    public function testHasRouteReturnsTrueWhenRouteIsSet()
    {
        $link = new Link('describedby');
        $link->setRoute('api/docs');
        $this->assertTrue($link->hasRoute());
    }

    public function testIsCompleteReturnsTrueWhenRouteIsSet()
    {
        $link = new Link('describedby');
        $link->setRoute('api/docs');
        $this->assertTrue($link->isComplete());
    }

    /**
     * @group 79
     */
    public function testFactoryCanGenerateLinkWithUrl()
    {
        $rel  = 'describedby';
        $url  = 'http://example.com/docs.html';
        $link = Link::factory(array(
            'rel' => $rel,
            'url' => $url,
        ));
        $this->assertInstanceOf('ZF\Hal\Link\Link', $link);
        $this->assertEquals($rel, $link->getRelation());
        $this->assertEquals($url, $link->getUrl());
    }

    /**
     * @group 79
     */
    public function testFactoryCanGenerateLinkWithRouteInformation()
    {
        $rel     = 'describedby';
        $route   = 'api/docs';
        $params  = array('version' => '1.1');
        $options = array('query' => 'version=1.1');
        $link = Link::factory(array(
            'rel'   => $rel,
            'route' => array(
                'name'    => $route,
                'params'  => $params,
                'options' => $options,
            ),
        ));

        $this->assertInstanceOf('ZF\Hal\Link\Link', $link);
        $this->assertEquals('describedby', $link->getRelation());
        $this->assertEquals($route, $link->getRoute());
        $this->assertEquals($params, $link->getRouteParams());
        $this->assertEquals($options, $link->getRouteOptions());
    }

    public function testFactoryCanGenerateLinkWithArbitraryProperties()
    {
        $rel = 'describedby';
        $url = 'http://example.org/api/foo?version=2';
        $link = Link::factory(array(
            'rel'   => $rel,
            'url'   => $url,
            'props' => array(
                'version' => 2,
                'latest'  => true,
            )
        ));

        $this->assertInstanceOf('ZF\Hal\Link\Link', $link);
        $this->assertEquals('describedby', $link->getRelation());
        $props = $link->getProps();
        $this->assertEquals(array(
            'version' => 2,
            'latest'  => true,
        ), $props);
    }
}
