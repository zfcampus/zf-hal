<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015-2017 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Extractor;

use PHPUnit\Framework\TestCase;
use Zend\Http\Request;
use Zend\Mvc\Router\Http\TreeRouteStack as V2TreeRouteStack;
use Zend\Mvc\Router\RouteMatch as V2RouteMatch;
use Zend\Router\Http\TreeRouteStack;
use Zend\Router\RouteMatch;
use Zend\View\Helper\ServerUrl;
use Zend\View\Helper\Url as UrlHelper;
use ZF\ApiProblem\Exception\DomainException;
use ZF\Hal\Extractor\LinkExtractor;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkUrlBuilder;

class LinkExtractorTest extends TestCase
{
    public function testExtractGivenIncompleteLinkShouldThrowException()
    {
        $linkUrlBuilder = $this->createMock(LinkUrlBuilder::class);
        $linkExtractor = new LinkExtractor($linkUrlBuilder);

        $link = $this->prophesize(Link::class);
        $link->isComplete()->willReturn(false)->shouldBeCalledTimes(1);

        $this->expectException(DomainException::class);
        $linkExtractor->extract($link->reveal());
    }

    public function testExtractGivenLinkWithUrlShouldReturnThisOne()
    {
        $linkUrlBuilder = $this->createMock(LinkUrlBuilder::class);
        $linkExtractor = new LinkExtractor($linkUrlBuilder);

        $params = [
            'rel' => 'resource',
            'url' => 'http://api.example.com',
        ];
        $link = Link::factory($params);

        $result = $linkExtractor->extract($link);

        $this->assertEquals($params['url'], $result['href']);
    }

    public function testExtractShouldComposeAnyPropertiesInLink()
    {
        $linkUrlBuilder = $this->createMock(LinkUrlBuilder::class);
        $linkExtractor = new LinkExtractor($linkUrlBuilder);

        $link = Link::factory([
            'rel'   => 'resource',
            'url'   => 'http://api.example.com/foo?version=2',
            'props' => [
                'version' => 2,
                'latest'  => true,
            ],
        ]);
        $result = $linkExtractor->extract($link);

        $expected = [
            'href'    => 'http://api.example.com/foo?version=2',
            'version' => 2,
            'latest'  => true,
        ];
        $this->assertEquals($expected, $result);
    }

    /**
     * @group 95
     */
    public function testPassingFalseReuseParamsOptionShouldOmitMatchedParametersInGeneratedLink()
    {
        $serverUrlHelper = $this->createMock(ServerUrl::class);
        $urlHelper       = new UrlHelper();

        $linkUrlBuilder = new LinkUrlBuilder($serverUrlHelper, $urlHelper);

        $linkExtractor = new LinkExtractor($linkUrlBuilder);

        $match = $this->matchUrl('/resource/foo', $urlHelper);
        $this->assertEquals('foo', $match->getParam('id', false));

        $link = Link::factory([
            'rel' => 'resource',
            'route' => [
                'name' => 'hostname/resource',
                'options' => [
                    'reuse_matched_params' => false,
                ],
            ],
        ]);

        $result = $linkExtractor->extract($link);

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('href', $result);
        $this->assertEquals('http://localhost.localdomain/resource', $result['href']);
    }

    private function matchUrl($url, $urlHelper)
    {
        $url     = 'http://localhost.localdomain' . $url;
        $request = new Request();
        $request->setUri($url);

        $routerClass = class_exists(V2TreeRouteStack::class) ? V2TreeRouteStack::class : TreeRouteStack::class;
        $router = new $routerClass();

        $router->addRoute('hostname', [
            'type' => 'hostname',
            'options' => [
                'route' => 'localhost.localdomain',
            ],
            'child_routes' => [
                'resource' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/resource[/:id]',
                    ],
                ],
            ],
        ]);

        $match = $router->match($request);
        if ($match instanceof RouteMatch || $match instanceof V2RouteMatch) {
            $urlHelper->setRouter($router);
            $urlHelper->setRouteMatch($match);
        }

        return $match;
    }
}
