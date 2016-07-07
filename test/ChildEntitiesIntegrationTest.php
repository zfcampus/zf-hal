<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Http\Request;
use Zend\Hydrator\HydratorPluginManager;
use Zend\Mvc\Controller\PluginManager as ControllerPluginManager;
use Zend\Mvc\Router\Http\TreeRouteStack as V2TreeRouteStack;
use Zend\Mvc\Router\RouteMatch as V2RouteMatch;
use Zend\Router\Http\TreeRouteStack;
use Zend\Router\RouteMatch;
use Zend\ServiceManager\ServiceManager;
use Zend\View\HelperPluginManager;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;
use Zend\View\Helper\Url as UrlHelper;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\Hal\Collection;
use ZF\Hal\Entity;
use ZF\Hal\Extractor\LinkCollectionExtractor;
use ZF\Hal\Extractor\LinkExtractor;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkUrlBuilder;
use ZF\Hal\Plugin\Hal as HalHelper;
use ZF\Hal\View\HalJsonModel;
use ZF\Hal\View\HalJsonRenderer;

/**
 * @subpackage UnitTest
 */
class ChildEntitiesIntegrationTest extends TestCase
{

    protected $router;
    protected $helpers;
    protected $renderer;

    public function setUp()
    {
        $this->setupRouter();
        $this->setupHelpers();
        $this->setupRenderer();
    }

    public function setupHelpers()
    {
        if (!$this->router) {
            $this->setupRouter();
        }

        $urlHelper = new UrlHelper();
        $urlHelper->setRouter($this->router);

        $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $linkUrlBuilder = new LinkUrlBuilder($serverUrlHelper, $urlHelper);

        $linksHelper = new HalHelper();
        $linksHelper->setLinkUrlBuilder($linkUrlBuilder);

        $linkExtractor = new LinkExtractor($linkUrlBuilder);
        $linkCollectionExtractor = new LinkCollectionExtractor($linkExtractor);
        $linksHelper->setLinkCollectionExtractor($linkCollectionExtractor);

        $this->helpers = $helpers = new HelperPluginManager(new ServiceManager());
        $helpers->setService('url', $urlHelper);
        $helpers->setService('serverUrl', $serverUrlHelper);
        $helpers->setService('Hal', $linksHelper);

        $this->plugins = $plugins = new ControllerPluginManager(new ServiceManager());
        $plugins->setService('Hal', $linksHelper);
    }

    public function setupRenderer()
    {
        if (!$this->helpers) {
            $this->setupHelpers();
        }
        $this->renderer = $renderer = new HalJsonRenderer(new ApiProblemRenderer());
        $renderer->setHelperPluginManager($this->helpers);
    }

    public function setupRouter()
    {
        $routes = [
            'parent' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/api/parent[/:parent]',
                    'defaults' => [
                        'controller' => 'Api\ParentController',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'child' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/child[/:child]',
                            'defaults' => [
                                'controller' => 'Api\ChildController',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $class = class_exists(V2TreeRouteStack::class) ? V2TreeRouteStack::class : TreeRouteStack::class;

        $this->router = $router = new $class();
        $router->addRoutes($routes);
    }

    public function setUpParentEntity()
    {
        $this->parent = (object) [
            'id'   => 'anakin',
            'name' => 'Anakin Skywalker',
        ];
        $entity = new Entity($this->parent, 'anakin');

        $link = new Link('self');
        $link->setRoute('parent');
        $link->setRouteParams(['parent'=> 'anakin']);
        $entity->getLinks()->add($link);

        return $entity;
    }

    public function setUpChildEntity($id, $name)
    {
        $this->child = (object) [
            'id'   => $id,
            'name' => $name,
        ];
        $entity = new Entity($this->child, $id);

        $link = new Link('self');
        $link->setRoute('parent/child');
        $link->setRouteParams(['child'=> $id]);
        $entity->getLinks()->add($link);

        return $entity;
    }

    public function setUpChildCollection()
    {
        $children = [
            ['luke', 'Luke Skywalker'],
            ['leia', 'Leia Organa'],
        ];
        $this->collection = [];
        foreach ($children as $info) {
            $this->collection[] = call_user_func_array([$this, 'setUpChildEntity'], $info);
        }
        $collection = new Collection($this->collection);
        $collection->setCollectionRoute('parent/child');
        $collection->setEntityRoute('parent/child');
        $collection->setPage(1);
        $collection->setPageSize(10);
        $collection->setCollectionName('child');

        $link = new Link('self');
        $link->setRoute('parent/child');
        $collection->getLinks()->add($link);

        return $collection;
    }

    public function testParentEntityRendersAsExpected()
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $routeClass = class_exists(V2RouteMatch::class) ? V2RouteMatch::class : RouteMatch::class;
        $this->assertInstanceOf($routeClass, $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertEquals('parent', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $parent = $this->setUpParentEntity();
        $model  = new HalJsonModel();
        $model->setPayload($parent);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin', $test->_links->self->href);
    }

    public function testChildEntityRendersAsExpected()
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin/child/luke';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $routeClass = class_exists(V2RouteMatch::class) ? V2RouteMatch::class : RouteMatch::class;
        $this->assertInstanceOf($routeClass, $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertEquals('luke', $matches->getParam('child'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $child = $this->setUpChildEntity('luke', 'Luke Skywalker');
        $model = new HalJsonModel();
        $model->setPayload($child);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child/luke', $test->_links->self->href);
    }

    public function testChildCollectionRendersAsExpected()
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin/child';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $routeClass = class_exists(V2RouteMatch::class) ? V2RouteMatch::class : RouteMatch::class;
        $this->assertInstanceOf($routeClass, $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertNull($matches->getParam('child'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $collection = $this->setUpChildCollection();
        $model = new HalJsonModel();
        $model->setPayload($collection);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child', $test->_links->self->href);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertObjectHasAttribute('child', $test->_embedded);
        $this->assertInternalType('array', $test->_embedded->child);
        $this->assertCount(2, $test->_embedded->child);

        foreach ($test->_embedded->child as $child) {
            $this->assertObjectHasAttribute('_links', $child);
            $this->assertObjectHasAttribute('self', $child->_links);
            $this->assertObjectHasAttribute('href', $child->_links->self);
            $this->assertRegExp(
                '#^http://localhost.localdomain/api/parent/anakin/child/[^/]+$#',
                $child->_links->self->href
            );
        }
    }

    public function setUpAlternateRouter()
    {
        $routes = [
            'parent' => [
                'type' => 'Segment',
                'options' => [
                    'route' => '/api/parent[/:id]',
                    'defaults' => [
                        'controller' => 'Api\ParentController',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'child' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/child[/:child]',
                            'defaults' => [
                                'controller' => 'Api\ChildController',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $class = class_exists(V2TreeRouteStack::class) ? V2TreeRouteStack::class : TreeRouteStack::class;
        $this->router = $router = new $class();
        $router->addRoutes($routes);
        $this->helpers->get('url')->setRouter($router);
    }

    public function testChildEntityObjectIdentifierMapping()
    {
        $this->setUpAlternateRouter();

        $uri = 'http://localhost.localdomain/api/parent/anakin/child/luke';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $routeClass = class_exists(V2RouteMatch::class) ? V2RouteMatch::class : RouteMatch::class;
        $this->assertInstanceOf($routeClass, $matches);
        $this->assertEquals('anakin', $matches->getParam('id'));
        $this->assertEquals('luke', $matches->getParam('child'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $child = $this->setUpChildEntity('luke', 'Luke Skywalker');
        $model = new HalJsonModel();
        $model->setPayload($child);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child/luke', $test->_links->self->href);
    }

    public function testChildEntityIdentifierMappingInsideCollection()
    {
        $this->setUpAlternateRouter();

        $uri = 'http://localhost.localdomain/api/parent/anakin/child';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $routeClass = class_exists(V2RouteMatch::class) ? V2RouteMatch::class : RouteMatch::class;
        $this->assertInstanceOf($routeClass, $matches);
        $this->assertEquals('anakin', $matches->getParam('id'));
        $this->assertNull($matches->getParam('child_id'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $collection = $this->setUpChildCollection();
        $model = new HalJsonModel();
        $model->setPayload($collection);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child', $test->_links->self->href);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertObjectHasAttribute('child', $test->_embedded);
        $this->assertInternalType('array', $test->_embedded->child);
        $this->assertCount(2, $test->_embedded->child);

        foreach ($test->_embedded->child as $child) {
            $this->assertObjectHasAttribute('_links', $child);
            $this->assertObjectHasAttribute('self', $child->_links);
            $this->assertObjectHasAttribute('href', $child->_links->self);
            $this->assertRegExp(
                '#^http://localhost.localdomain/api/parent/anakin/child/[^/]+$#',
                $child->_links->self->href
            );
        }
    }
}
