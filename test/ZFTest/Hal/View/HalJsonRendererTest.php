<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZFTest\Hal\View;

use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;
use Zend\Mvc\Router\Http\Segment;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\Paginator\Adapter\ArrayAdapter;
use Zend\Paginator\Paginator;
use Zend\Stdlib\Hydrator;
use Zend\View\HelperPluginManager;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\Hal\Collection;
use ZF\Hal\Resource;
use ZF\Hal\Link\Link;
use ZF\Hal\Plugin\Hal as HalHelper;
use ZF\Hal\View\HalJsonModel;
use ZF\Hal\View\HalJsonRenderer;
use ZFTest\Hal\TestAsset;

/**
 * @subpackage UnitTest
 */
class HalJsonRendererTest extends TestCase
{
    public function setUp()
    {
        $this->renderer = new HalJsonRenderer(new ApiProblemRenderer());
    }

    public function assertIsResource($resource)
    {
        $this->assertInstanceOf('stdClass', $resource, 'Invalid HAL resource; not an object');
        $this->assertObjectHasAttribute('_links', $resource, 'Invalid HAL resource; does not contain links');
        $links = $resource->_links;
        $this->assertInstanceOf('stdClass', $links, 'Invalid HAL resource; links are not an object');
    }

    public function assertResourceHasRelationalLink($relation, $resource)
    {
        $this->assertIsResource($resource);
        $links = $resource->_links;
        $this->assertObjectHasAttribute($relation, $links, sprintf('HAL links do not contain relation "%s"', $relation));
        $link = $links->{$relation};
        $this->assertInstanceOf('stdClass', $link, sprintf('Relational links for "%s" are malformed', $relation));
    }

    public function assertRelationalLinkContains($match, $relation, $resource)
    {
        $this->assertResourceHasRelationalLink($relation, $resource);
        $link = $resource->_links->{$relation};
        $this->assertObjectHasAttribute('href', $link, sprintf('%s relational link does not have an href attribute; received %s', $relation, var_export($link, 1)));
        $href = $link->href;
        $this->assertContains($match, $href);
    }

    public function assertRelationalLinkEquals($match, $relation, $resource)
    {
        $this->assertResourceHasRelationalLink($relation, $resource);
        $link = $resource->_links->{$relation};
        $this->assertObjectHasAttribute('href', $link, sprintf('%s relational link does not have an href attribute; received %s', $relation, var_export($link, 1)));
        $href = $link->href;
        $this->assertEquals($match, $href);
    }

    public function nonHalJsonModels()
    {
        return array(
            'view-model' => array(new ViewModel(array('foo' => 'bar'))),
            'json-view-model' => array(new JsonModel(array('foo' => 'bar'))),
        );
    }

    /**
     * @dataProvider nonHalJsonModels
     */
    public function testPassesNonHalJsonModelToParentToRender($model)
    {
        $payload = $this->renderer->render($model);
        $expected = json_encode(array('foo' => 'bar'));
        $this->assertEquals($expected, $payload);
    }

    public function setUpHelpers()
    {
        // need to setup routes
        // need to get a url and serverurl helper that have appropriate injections
        $this->router = $router = new TreeRouteStack();
        $this->resourceRoute = new Segment('/resource[/[:id]]');
        $this->router->addRoute('resource', $this->resourceRoute);

        $this->helpers = $helpers  = new HelperPluginManager();
        $serverUrl = $helpers->get('ServerUrl');
        $url       = $helpers->get('url');
        $url->setRouter($router);
        $serverUrl->setScheme('http');
        $serverUrl->setHost('localhost.localdomain');
        $halLinks  = new HalHelper();
        $halLinks->setServerUrlHelper($serverUrl);
        $halLinks->setUrlHelper($url);
        $helpers->setService('Hal', $halLinks);

        $this->renderer->setHelperPluginManager($helpers);
    }

    public function testRendersResourceWithAssociatedLinks()
    {
        $this->setUpHelpers();

        $item = new Resource(array(
            'foo' => 'bar',
            'id'  => 'identifier',
        ), 'identifier');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(array('id' => 'identifier'));
        $links->add($self);

        $model = new HalJsonModel(array('payload' => $item));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/identifier', 'self', $test);
        $this->assertObjectHasAttribute('foo', $test);
        $this->assertEquals('bar', $test->foo);
    }

    public function testCanRenderStdclassResource()
    {
        $this->setUpHelpers();

        $item = (object) array(
            'foo' => 'bar',
            'id'  => 'identifier',
        );

        $item  = new Resource($item, 'identifier', 'resource');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(array('id' => 'identifier'));
        $links->add($self);

        $model = new HalJsonModel(array('payload' => $item));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/identifier', 'self', $test);
        $this->assertObjectHasAttribute('foo', $test);
        $this->assertEquals('bar', $test->foo);
    }

    public function testCanSerializeHydratableResource()
    {
        $this->setUpHelpers();
        $this->helpers->get('Hal')->addHydrator(
            'ZFTest\Hal\TestAsset\ArraySerializable',
            new Hydrator\ArraySerializable()
        );

        $item  = new TestAsset\ArraySerializable();
        $item  = new Resource(new TestAsset\ArraySerializable(), 'identifier', 'resource');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(array('id' => 'identifier'));
        $links->add($self);

        $model = new HalJsonModel(array('payload' => $item));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/identifier', 'self', $test);
        $this->assertObjectHasAttribute('foo', $test);
        $this->assertEquals('bar', $test->foo);
    }

    public function testUsesDefaultHydratorIfAvailable()
    {
        $this->setUpHelpers();
        $this->helpers->get('Hal')->setDefaultHydrator(
            new Hydrator\ArraySerializable()
        );

        $item  = new TestAsset\ArraySerializable();
        $item  = new Resource(new TestAsset\ArraySerializable(), 'identifier', 'resource');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(array('id' => 'identifier'));
        $links->add($self);

        $model = new HalJsonModel(array('payload' => $item));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/identifier', 'self', $test);
        $this->assertObjectHasAttribute('foo', $test);
        $this->assertEquals('bar', $test->foo);
    }

    public function testCanRenderNonPaginatedCollection()
    {
        $this->setUpHelpers();

        $prototype = array('foo' => 'bar');
        $items = array();
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }

        $collection = new Collection($items);
        $collection->setCollectionRoute('resource');
        $collection->setResourceRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $model      = new HalJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource', 'self', $test);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertInstanceof('stdClass', $test->_embedded);
        $this->assertObjectHasAttribute('items', $test->_embedded);
        $this->assertInternalType('array', $test->_embedded->items);
        $this->assertEquals(100, count($test->_embedded->items));

        foreach ($test->_embedded->items as $key => $item) {
            $id = $key + 1;

            $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/' . $id, 'self', $item);
            $this->assertObjectHasAttribute('id', $item, var_export($item, 1));
            $this->assertEquals($id, $item->id);
            $this->assertObjectHasAttribute('foo', $item);
            $this->assertEquals('bar', $item->foo);
        }
    }

    public function testCanRenderPaginatedCollection()
    {
        $this->setUpHelpers();

        $prototype = array('foo' => 'bar');
        $items = array();
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }
        $adapter   = new ArrayAdapter($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(3);
        $collection->setCollectionRoute('resource');
        $collection->setResourceRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $model      = new HalJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource?page=3', 'self', $test);
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource', 'first', $test);
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource?page=20', 'last', $test);
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource?page=2', 'prev', $test);
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource?page=4', 'next', $test);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertInstanceof('stdClass', $test->_embedded);
        $this->assertObjectHasAttribute('items', $test->_embedded);
        $this->assertInternalType('array', $test->_embedded->items);
        $this->assertEquals(5, count($test->_embedded->items));

        foreach ($test->_embedded->items as $key => $item) {
            $id = $key + 11;

            $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/' . $id, 'self', $item);
            $this->assertObjectHasAttribute('id', $item, var_export($item, 1));
            $this->assertEquals($id, $item->id);
            $this->assertObjectHasAttribute('foo', $item);
            $this->assertEquals('bar', $item->foo);
        }
    }

    public function invalidPages()
    {
        return array(
            '-1'   => array(-1),
            '1000' => array(1000),
        );
    }

    /**
     * @dataProvider invalidPages
     */
    public function testRenderingPaginatedCollectionCanReturnApiProblemIfPageIsTooHighOrTooLow($page)
    {
        $this->setUpHelpers();

        $prototype = array('foo' => 'bar');
        $items = array();
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }
        $adapter   = new ArrayAdapter($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator, 'resource');
        $collection->setPageSize(5);

        // Using reflection object so we can force a negative page number if desired
        $r = new ReflectionObject($collection);
        $p = $r->getProperty('page');
        $p->setAccessible(true);
        $p->setValue($collection, $page);

        $model      = new HalJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertObjectHasAttribute('httpStatus', $test, var_export($test, 1));
        $this->assertEquals(409, $test->httpStatus);
        $this->assertObjectHasAttribute('detail', $test);
        $this->assertEquals('Invalid page provided', $test->detail);
    }

    public function testRendersAttributesAsPartOfNonPaginatedCollection()
    {
        $this->setUpHelpers();

        $attributes = array(
            'count' => 100,
            'type'  => 'foo',
        );

        $prototype = array('foo' => 'bar');
        $items = array();
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }

        $collection = new Collection($items, 'resource');
        $collection->setAttributes($attributes);

        $model      = new HalJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $this->assertObjectHasAttribute('count', $test, var_export($test, 1));
        $this->assertEquals(100, $test->count);
        $this->assertObjectHasAttribute('type', $test);
        $this->assertEquals('foo', $test->type);
    }

    public function testRendersAttributeAsPartOfPaginatedCollectionResource()
    {
        $this->setUpHelpers();

        $attributes = array(
            'count' => 100,
            'type'  => 'foo',
        );

        $prototype = array('foo' => 'bar');
        $items = array();
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }
        $adapter   = new ArrayAdapter($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(3);
        $collection->setAttributes($attributes);
        $collection->setCollectionRoute('resource');
        $collection->setResourceRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $model      = new HalJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $this->assertObjectHasAttribute('count', $test, var_export($test, 1));
        $this->assertEquals(100, $test->count);
        $this->assertObjectHasAttribute('type', $test);
        $this->assertEquals('foo', $test->type);
    }

    public function testCanRenderNestedResourcesAsEmbeddedResources()
    {
        $this->setUpHelpers();
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new Resource(array(
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ), 'matthew', 'user');
        $link = new Link('self');
        $link->setRoute('user')->setRouteParams(array('id' => 'matthew'));
        $child->getLinks()->add($link);

        $item = new Resource(array(
            'foo'  => 'bar',
            'id'   => 'identifier',
            'user' => $child,
        ), 'identifier', 'resource');
        $link = new Link('self');
        $link->setRoute('resource')->setRouteParams(array('id' => 'identifier'));
        $item->getLinks()->add($link);

        $model = new HalJsonModel(array('payload' => $item));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertObjectNotHasAttribute('user', $test);
        $this->assertObjectHasAttribute('_embedded', $test);
        $embedded = $test->_embedded;
        $this->assertObjectHasAttribute('user', $embedded);
        $user = $embedded->user;
        $this->assertRelationalLinkContains('/user/matthew', 'self', $user);
        $user = (array) $user;
        foreach ($child->resource as $key => $value) {
            $this->assertArrayHasKey($key, $user);
            $this->assertEquals($value, $user[$key]);
        }
    }

    public function testRendersEmbeddedResourcesOfIndividualNonPaginatedCollectionResources()
    {
        $this->setUpHelpers();
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new Resource(array(
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ), 'matthew', 'user');
        $link = new Link('self');
        $link->setRoute('user')->setRouteParams(array('id' => 'matthew'));
        $child->getLinks()->add($link);

        $prototype = array('foo' => 'bar', 'user' => $child);
        $items = array();
        foreach (range(1, 3) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }

        $collection = new Collection($items);
        $collection->setCollectionRoute('resource');
        $collection->setResourceRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $model = new HalJsonModel(array('payload' => $collection));
        $test  = $this->renderer->render($model);
        $test  = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $collection = $test->_embedded->items;
        foreach ($collection as $item) {
            $this->assertObjectHasAttribute('_embedded', $item);
            $embedded = $item->_embedded;
            $this->assertObjectHasAttribute('user', $embedded);
            $user = $embedded->user;
            $this->assertRelationalLinkContains('/user/matthew', 'self', $user);
            $user = (array) $user;
            foreach ($child->resource as $key => $value) {
                $this->assertArrayHasKey($key, $user);
                $this->assertEquals($value, $user[$key]);
            }
        }
    }

    public function testRendersEmbeddedResourcesOfIndividualPaginatedCollectionResources()
    {
        $this->setUpHelpers();
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new Resource(array(
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ), 'matthew', 'user');
        $link = new Link('self');
        $link->setRoute('user')->setRouteParams(array('id' => 'matthew'));
        $child->getLinks()->add($link);

        $prototype = array('foo' => 'bar', 'user' => $child);
        $items = array();
        foreach (range(1, 3) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;

        }
        $adapter   = new ArrayAdapter($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(1);
        $collection->setCollectionRoute('resource');
        $collection->setResourceRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $model      = new HalJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $collection = $test->_embedded->items;
        foreach ($collection as $item) {
            $this->assertObjectHasAttribute('_embedded', $item, var_export($item, 1));
            $embedded = $item->_embedded;
            $this->assertObjectHasAttribute('user', $embedded);
            $user = $embedded->user;
            $this->assertRelationalLinkContains('/user/matthew', 'self', $user);
            $user = (array) $user;
            foreach ($child->resource as $key => $value) {
                $this->assertArrayHasKey($key, $user);
                $this->assertEquals($value, $user[$key]);
            }
        }
    }

    public function testAllowsSpecifyingAlternateCallbackForReturningResourceId()
    {
        $this->setUpHelpers();

        $this->helpers->get('Hal')->getEventManager()->attach('getIdFromResource', function ($e) {
            $resource = $e->getParam('resource');

            if (!is_array($resource)) {
                return false;
            }

            if (array_key_exists('name', $resource)) {
                return $resource['name'];
            }

            return false;
        }, 10);


        $prototype = array('foo' => 'bar');
        $items = array();
        foreach (range(1, 100) as $id) {
            $item         = $prototype;
            $item['name'] = $id;
            $items[]      = $item;
        }

        $collection = new Collection($items);
        $collection->setCollectionRoute('resource');
        $collection->setResourceRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $model      = new HalJsonModel(array('payload' => $collection));
        $test       = $this->renderer->render($model);
        $test       = json_decode($test);

        $this->assertInstanceof('stdClass', $test, var_export($test, 1));
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource', 'self', $test);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertInstanceof('stdClass', $test->_embedded);
        $this->assertObjectHasAttribute('items', $test->_embedded);
        $this->assertInternalType('array', $test->_embedded->items);
        $this->assertEquals(100, count($test->_embedded->items));

        foreach ($test->_embedded->items as $key => $item) {
            $id = $key + 1;

            $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/' . $id, 'self', $item);
            $this->assertObjectHasAttribute('name', $item, var_export($item, 1));
            $this->assertEquals($id, $item->name);
            $this->assertObjectHasAttribute('foo', $item);
            $this->assertEquals('bar', $item->foo);
        }
    }
}
