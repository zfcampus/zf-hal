<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2013 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Plugin;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Http\Request;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\Mvc\Router\RouteMatch;
use Zend\Mvc\Router\SimpleRouteStack;
use Zend\Mvc\Router\Http\Segment;
use Zend\Mvc\MvcEvent;
use Zend\Uri\Http;
use Zend\Uri\Uri;
use Zend\View\Helper\Url as UrlHelper;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;
use ZF\Hal\Collection;
use ZF\Hal\Resource;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkCollection;
use ZF\Hal\Metadata\MetadataMap;
use ZF\Hal\Plugin\Hal as HalHelper;

/**
 * @subpackage UnitTest
 */
class HalTest extends TestCase
{
    public function setUp()
    {
        $this->router = $router = new TreeRouteStack();
        $route = new Segment('/resource[/[:id]]');
        $router->addRoute('resource', $route);
        $route2 = new Segment('/help');
        $router->addRoute('docs', $route2);
        $router->addRoute('hostname', array(

            'type' => 'hostname',
            'options' => array(
                'route' => 'localhost.localdomain',
            ),

            'child_routes' => array(
                'resource' => array(
                    'type' => 'segment',
                    'options' => array(
                        'route' => '/resource[/:id]'
                    ),
                    'may_terminate' => true,
                    'child_routes' => array(
                        'children' => array(
                            'type' => 'literal',
                            'options' => array(
                                'route' => '/children',
                            ),
                        ),
                    ),
                ),
                'users' => array(
                    'type' => 'segment',
                    'options' => array(
                        'route' => '/users[/:id]'
                    )
                ),
                'contacts' => array(
                    'type' => 'segment',
                    'options' => array(
                        'route' => '/contacts[/:id]'
                    )
                ),
                'embedded' => array(
                    'type' => 'segment',
                    'options' => array(
                        'route' => '/embedded[/:id]'
                    )
                ),
                'embedded_custom' => array(
                    'type' => 'segment',
                    'options' => array(
                        'route' => '/embedded_custom[/:custom_id]'
                    )
                ),
            )
        ));

        $this->event = $event = new MvcEvent();
        $event->setRouter($router);
        $router->setRequestUri(new Http('http://localhost.localdomain/resource'));

        $controller = $this->controller = $this->getMock('Zend\Mvc\Controller\AbstractRestfulController');
        $controller->expects($this->any())
            ->method('getEvent')
            ->will($this->returnValue($event));

        $this->urlHelper = $urlHelper = new UrlHelper();
        $urlHelper->setRouter($router);

        $this->serverUrlHelper = $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $this->plugin = $plugin = new HalHelper();
        $plugin->setController($controller);
        $plugin->setUrlHelper($urlHelper);
        $plugin->setServerUrlHelper($serverUrlHelper);
    }

    public function assertRelationalLinkContains($match, $relation, $resource)
    {
        $this->assertInternalType('array', $resource);
        $this->assertArrayHasKey('_links', $resource);
        $links = $resource['_links'];
        $this->assertInternalType('array', $links);
        $this->assertArrayHasKey($relation, $links);
        $link = $links[$relation];
        $this->assertInternalType('array', $link);
        $this->assertArrayHasKey('href', $link);
        $href = $link['href'];
        $this->assertInternalType('string', $href);
        $this->assertContains($match, $href);
    }

    public function testCreateLinkSkipServerUrlHelperIfSchemeExists()
    {
        $url = $this->plugin->createLink('hostname/resource');
        $this->assertEquals('http://localhost.localdomain/resource', $url);
    }

    public function testLinkCreationWithoutIdCreatesFullyQualifiedLink()
    {
        $url = $this->plugin->createLink('resource');
        $this->assertEquals('http://localhost.localdomain/resource', $url);
    }

    public function testLinkCreationWithIdCreatesFullyQualifiedLink()
    {
        $url = $this->plugin->createLink('resource', 123);
        $this->assertEquals('http://localhost.localdomain/resource/123', $url);
    }

    public function testLinkCreationFromResource()
    {
        $self = new Link('self');
        $self->setRoute('resource', array('id' => 123));
        $docs = new Link('describedby');
        $docs->setRoute('docs');
        $resource = new Resource(array(), 123);
        $resource->getLinks()->add($self)->add($docs);
        $links = $this->plugin->fromResource($resource);

        $this->assertInternalType('array', $links);
        $this->assertArrayHasKey('self', $links, var_export($links, 1));
        $this->assertArrayHasKey('describedby', $links, var_export($links, 1));

        $selfLink = $links['self'];
        $this->assertInternalType('array', $selfLink);
        $this->assertArrayHasKey('href', $selfLink);
        $this->assertEquals('http://localhost.localdomain/resource/123', $selfLink['href']);

        $docsLink = $links['describedby'];
        $this->assertInternalType('array', $docsLink);
        $this->assertArrayHasKey('href', $docsLink);
        $this->assertEquals('http://localhost.localdomain/help', $docsLink['href']);
    }

    public function testRendersEmbeddedCollectionsInsideResources()
    {
        $collection = new Collection(
            array(
                (object) array('id' => 'foo', 'name' => 'foo'),
                (object) array('id' => 'bar', 'name' => 'bar'),
                (object) array('id' => 'baz', 'name' => 'baz'),
            ),
            'hostname/contacts'
        );
        $resource = new Resource(
            (object) array(
                'id'       => 'user',
                'contacts' => $collection,
            ),
            'user'
        );
        $self = new Link('self');
        $self->setRoute('hostname/users', array('id' => 'user'));
        $resource->getLinks()->add($self);

        $rendered = $this->plugin->renderResource($resource);
        $this->assertRelationalLinkContains('/users/', 'self', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertArrayHasKey('contacts', $embed);
        $contacts = $embed['contacts'];
        $this->assertInternalType('array', $contacts);
        $this->assertEquals(3, count($contacts));
        foreach ($contacts as $contact) {
            $this->assertInternalType('array', $contact);
            $this->assertRelationalLinkContains('/contacts/', 'self', $contact);
        }
    }

    public function testRendersEmbeddedResourcesInsideResourcesBasedOnMetadataMap()
    {
        $object = new TestAsset\Resource('foo', 'Foo');
        $object->first_child  = new TestAsset\EmbeddedResource('bar', 'Bar');
        $object->second_child = new TestAsset\EmbeddedResourceWithCustomIdentifier('baz', 'Baz');
        $resource = new Resource($object, 'foo');
        $self = new Link('self');
        $self->setRoute('hostname/resource', array('id' => 'foo'));
        $resource->getLinks()->add($self);

        $metadata = new MetadataMap(array(
            'ZFTest\Hal\Plugin\TestAsset\Resource' => array(
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ),
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedResource' => array(
                'hydrator' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ),
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedResourceWithCustomIdentifier' => array(
                'hydrator'        => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'           => 'hostname/embedded_custom',
                'route_identifier_name' => 'custom_id',
                'entity_identifier_name' => 'custom_id',
            ),
        ));

        $this->plugin->setMetadataMap($metadata);

        $rendered = $this->plugin->renderResource($resource);
        $this->assertRelationalLinkContains('/resource/foo', 'self', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertEquals(2, count($embed));
        $this->assertArrayHasKey('first_child', $embed);
        $this->assertArrayHasKey('second_child', $embed);

        $first = $embed['first_child'];
        $this->assertInternalType('array', $first);
        $this->assertRelationalLinkContains('/embedded/bar', 'self', $first);

        $second = $embed['second_child'];
        $this->assertInternalType('array', $second);
        $this->assertRelationalLinkContains('/embedded_custom/baz', 'self', $second);
    }

    public function testMetadataMapLooksForParentClasses()
    {
        $object = new TestAsset\Resource('foo', 'Foo');
        $object->first_child  = new TestAsset\EmbeddedProxyResource('bar', 'Bar');
        $object->second_child = new TestAsset\EmbeddedProxyResourceWithCustomIdentifier('baz', 'Baz');
        $resource = new Resource($object, 'foo');
        $self = new Link('self');
        $self->setRoute('hostname/resource', array('id' => 'foo'));
        $resource->getLinks()->add($self);

        $metadata = new MetadataMap(array(
            'ZFTest\Hal\Plugin\TestAsset\Resource' => array(
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ),
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedResource' => array(
                'hydrator' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ),
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedResourceWithCustomIdentifier' => array(
                'hydrator'        => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'           => 'hostname/embedded_custom',
                'route_identifier_name' => 'custom_id',
                'entity_identifier_name' => 'custom_id',
            ),
        ));

        $this->plugin->setMetadataMap($metadata);

        $rendered = $this->plugin->renderResource($resource);
        $this->assertRelationalLinkContains('/resource/foo', 'self', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertEquals(2, count($embed));
        $this->assertArrayHasKey('first_child', $embed);
        $this->assertArrayHasKey('second_child', $embed);

        $first = $embed['first_child'];
        $this->assertInternalType('array', $first);
        $this->assertRelationalLinkContains('/embedded/bar', 'self', $first);

        $second = $embed['second_child'];
        $this->assertInternalType('array', $second);
        $this->assertRelationalLinkContains('/embedded_custom/baz', 'self', $second);
    }

    public function testRendersEmbeddedCollectionsInsideResourcesBasedOnMetadataMap()
    {
        $collection = new TestAsset\Collection(
            array(
                (object) array('id' => 'foo', 'name' => 'foo'),
                (object) array('id' => 'bar', 'name' => 'bar'),
                (object) array('id' => 'baz', 'name' => 'baz'),
            )
        );

        $metadata = new MetadataMap(array(
            'ZFTest\Hal\Plugin\TestAsset\Collection' => array(
                'is_collection'       => true,
                'collection_name'     => 'collection', // should be overridden
                'route_name'          => 'hostname/contacts',
                'resource_route_name' => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ),
        ));

        $this->plugin->setMetadataMap($metadata);

        $resource = new Resource(
            (object) array(
                'id'       => 'user',
                'contacts' => $collection,
            ),
            'user'
        );
        $self = new Link('self');
        $self->setRoute('hostname/users', array('id' => 'user'));
        $resource->getLinks()->add($self);

        $rendered = $this->plugin->renderResource($resource);

        $this->assertRelationalLinkContains('/users/', 'self', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertArrayHasKey('contacts', $embed);
        $contacts = $embed['contacts'];
        $this->assertInternalType('array', $contacts);
        $this->assertEquals(3, count($contacts));
        foreach ($contacts as $contact) {
            $this->assertInternalType('array', $contact);
            $this->assertArrayHasKey('id', $contact);
            $this->assertRelationalLinkContains('/embedded/' . $contact['id'], 'self', $contact);
        }
    }

    public function testRendersEmbeddedCollectionsInsideCollectionsBasedOnMetadataMap()
    {
        $childCollection = new TestAsset\Collection(
            array(
                (object) array('id' => 'foo', 'name' => 'foo'),
                (object) array('id' => 'bar', 'name' => 'bar'),
                (object) array('id' => 'baz', 'name' => 'baz'),
            )
        );
        $resource = new TestAsset\Resource('spock', 'Spock');
        $resource->first_child = $childCollection;

        $metadata = new MetadataMap(array(
            'ZFTest\Hal\Plugin\TestAsset\Collection' => array(
                'is_collection'  => true,
                'route'          => 'hostname/contacts',
                'resource_route' => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ),
            'ZFTest\Hal\Plugin\TestAsset\Resource' => array(
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ),
        ));

        $this->plugin->setMetadataMap($metadata);

        $collection = new Collection(array($resource), 'hostname/resource');
        $self = new Link('self');
        $self->setRoute('hostname/resource');
        $collection->getLinks()->add($self);
        $collection->setCollectionName('resources');

        $rendered = $this->plugin->renderCollection($collection);

        $this->assertRelationalLinkContains('/resource', 'self', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertArrayHasKey('resources', $embed);
        $resources = $embed['resources'];
        $this->assertInternalType('array', $resources);
        $this->assertEquals(1, count($resources));

        $resource = array_shift($resources);
        $this->assertInternalType('array', $resource);
        $this->assertArrayHasKey('_embedded', $resource);
        $this->assertInternalType('array', $resource['_embedded']);
        $this->assertArrayHasKey('first_child', $resource['_embedded']);
        $this->assertInternalType('array', $resource['_embedded']['first_child']);

        foreach ($resource['_embedded']['first_child'] as $contact) {
            $this->assertInternalType('array', $contact);
            $this->assertArrayHasKey('id', $contact);
            $this->assertRelationalLinkContains('/embedded/' . $contact['id'], 'self', $contact);
        }
    }

    public function testDoesNotRenderEmbeddedResourcesInsideCollectionsBasedOnMetadataMapAndRenderEmbeddedResourcesAsFalse()
    {

        $resource = new TestAsset\Resource('spock', 'Spock');
        $resource->first_child  = new TestAsset\EmbeddedResource('bar', 'Bar');
        $resource->second_child = new TestAsset\EmbeddedResourceWithCustomIdentifier('baz', 'Baz');

        $metadata = new MetadataMap(array(
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedResource' => array(
                'hydrator' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
            ),
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedResourceWithCustomIdentifier' => array(
                'hydrator'        => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'           => 'hostname/embedded_custom',
                'route_identifier_name' => 'custom_id',
                'entity_identifier_name' => 'custom_id',
            ),

            'ZFTest\Hal\Plugin\TestAsset\Collection' => array(
                'is_collection'  => true,
                'route'          => 'hostname/contacts',
                'resource_route' => 'hostname/embedded',
            ),
            'ZFTest\Hal\Plugin\TestAsset\Resource' => array(
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
            ),
        ));

        $this->plugin->setMetadataMap($metadata);
        $this->plugin->setRenderEmbeddedResources(false);

        $collection = new Collection(array($resource), 'hostname/resource');
        $self = new Link('self');
        $self->setRoute('hostname/resource');
        $collection->getLinks()->add($self);
        $collection->setCollectionName('resources');

        $rendered = $this->plugin->renderCollection($collection);

        $this->assertRelationalLinkContains('/resource', 'self', $rendered);

        $this->assertArrayHasKey('_embedded', $rendered);
        $embed = $rendered['_embedded'];
        $this->assertArrayHasKey('resources', $embed);
        $resources = $embed['resources'];
        $this->assertInternalType('array', $resources);
        $this->assertEquals(1, count($resources));

        $resource = array_shift($resources);
        $this->assertInternalType('array', $resource);
        $this->assertArrayHasKey('_embedded', $resource);
        $this->assertInternalType('array', $resource['_embedded']);

        foreach ($resource['_embedded']['first_child'] as $contact) {
            $this->assertInternalType('array', $contact);
            $this->assertArrayNotHasKey('id', $contact);
        }
    }

    public function testWillNotAllowInjectingASelfRelationMultipleTimes()
    {
        $resource = new Resource(array(
            'id'  => 1,
            'foo' => 'bar',
        ), 1);
        $links = $resource->getLinks();

        $this->assertFalse($links->has('self'));

        $this->plugin->injectSelfLink($resource, 'hostname/resource');

        $this->assertTrue($links->has('self'));
        $link = $links->get('self');
        $this->assertInstanceof('ZF\Hal\Link\Link', $link);

        $this->plugin->injectSelfLink($resource, 'hostname/resource');
        $this->assertTrue($links->has('self'));
        $link = $links->get('self');
        $this->assertInstanceof('ZF\Hal\Link\Link', $link);
    }

    public function testResoucePropertiesCanBeLinks()
    {
        $embeddedLink = new Link('embeddedLink');
        $embeddedLink->setRoute('hostname/contacts', array('id' => 'bar'));

        $properties = array(
            'id' => '10',
            'embeddedLink' => $embeddedLink,
        );

        $resource = new Resource((object) $properties, 'foo');

        $rendered = $this->plugin->renderResource($resource);

        $this->assertArrayHasKey('_links', $rendered);
        $this->assertArrayHasKey('embeddedLink', $rendered['_links']);
        $this->assertArrayNotHasKey('embeddedLink', $rendered);
        $this->assertArrayHasKey('href', $rendered['_links']['embeddedLink']);
        $this->assertEquals('http://localhost.localdomain/contacts/bar', $rendered['_links']['embeddedLink']['href']);
    }

    public function testResourcePropertyLinksUseHref()
    {
        $link1 = new Link('link1');
        $link1->setUrl('link1');

        $link2 = new Link('link2');
        $link2->setUrl('link2');

        $properties = array(
            'id' => '10',
            'bar' => $link1,
            'baz' => $link2,
        );

        $resource = new Resource((object) $properties, 'foo');

        $rendered = $this->plugin->renderResource($resource);

        $this->assertArrayHasKey('_links', $rendered);
        $this->assertArrayHasKey('link1', $rendered['_links']);
        $this->assertArrayNotHasKey('bar', $rendered['_links']);
        $this->assertArrayNotHasKey('link1', $rendered);

        $this->assertArrayHasKey('link2', $rendered['_links']);
        $this->assertArrayNotHasKey('baz', $rendered['_links']);
        $this->assertArrayNotHasKey('link2', $rendered);
    }

    public function testResoucePropertiesCanBeLinkCollections()
    {
        $link = new Link('embeddedLink');
        $link->setRoute('hostname/contacts', array('id' => 'bar'));

        //simple link
        $collection = new LinkCollection();
        $collection->add($link);

        //array of links
        $linkArray = new Link('arrayLink');
        $linkArray->setRoute('hostname/contacts', array('id' => 'bar'));
        $collection->add($linkArray);

        $linkArray = new Link('arrayLink');
        $linkArray->setRoute('hostname/contacts', array('id' => 'baz'));
        $collection->add($linkArray);

        $properties = array(
            'id' => '10',
            'links' => $collection,
        );

        $resource = new Resource((object) $properties, 'foo');

        $rendered = $this->plugin->renderResource($resource);

        $this->assertArrayHasKey('_links', $rendered);
        $this->assertArrayHasKey('embeddedLink', $rendered['_links']);
        $this->assertArrayNotHasKey('embeddedLink', $rendered);
        $this->assertArrayHasKey('href', $rendered['_links']['embeddedLink']);
        $this->assertEquals('http://localhost.localdomain/contacts/bar', $rendered['_links']['embeddedLink']['href']);

        $this->assertArrayHasKey('arrayLink', $rendered['_links']);
        $this->assertCount(2, $rendered['_links']['arrayLink']);
    }

    /**
     * @group 71
     */
    public function testRenderingEmbeddedResourceEmbedsResource()
    {
        $embedded = new Resource((object) array('id' => 'foo', 'name' => 'foo'), 'foo');
        $self = new Link('self');
        $self->setRoute('hostname/contacts', array('id' => 'foo'));
        $embedded->getLinks()->add($self);

        $resource = new Resource((object) array('id' => 'user', 'contact' => $embedded), 'user');
        $self = new Link('self');
        $self->setRoute('hostname/users', array('id' => 'user'));
        $resource->getLinks()->add($self);

        $rendered = $this->plugin->renderResource($resource);

        $this->assertRelationalLinkContains('/users/user', 'self', $rendered);
        $this->assertArrayHasKey('_embedded', $rendered);
        $this->assertInternalType('array', $rendered['_embedded']);
        $this->assertArrayHasKey('contact', $rendered['_embedded']);
        $contact = $rendered['_embedded']['contact'];
        $this->assertRelationalLinkContains('/contacts/foo', 'self', $contact);
    }

    /**
     * @group 71
     */
    public function testRenderingCollectionRendersAllLinksInEmbeddedResources()
    {
        $embedded = new Resource((object) array('id' => 'foo', 'name' => 'foo'), 'foo');
        $links = $embedded->getLinks();
        $self = new Link('self');
        $self->setRoute('hostname/users', array('id' => 'foo'));
        $links->add($self);
        $phones = new Link('phones');
        $phones->setUrl('http://localhost.localdomain/users/foo/phones');
        $links->add($phones);

        $collection = new Collection(array($embedded));
        $collection->setCollectionName('users');
        $self = new Link('self');
        $self->setRoute('hostname/users');
        $collection->getLinks()->add($self);

        $rendered = $this->plugin->renderCollection($collection);

        $this->assertRelationalLinkContains('/users', 'self', $rendered);
        $this->assertArrayHasKey('_embedded', $rendered);
        $this->assertInternalType('array', $rendered['_embedded']);
        $this->assertArrayHasKey('users', $rendered['_embedded']);

        $users = $rendered['_embedded']['users'];
        $this->assertInternalType('array', $users);
        $user = array_shift($users);

        $this->assertRelationalLinkContains('/users/foo', 'self', $user);
        $this->assertRelationalLinkContains('/users/foo/phones', 'phones', $user);
    }

    public function testResourcesFromCollectionCanUseHydratorSetInMetadataMap()
    {
        $object   = new TestAsset\ResourceWithProtectedProperties('foo', 'Foo');
        $resource = new Resource($object, 'foo');

        $metadata = new MetadataMap(array(
            'ZFTest\Hal\Plugin\TestAsset\ResourceWithProtectedProperties' => array(
                'hydrator'   => 'ArraySerializable',
                'route_name' => 'hostname/resource',
            ),
        ));

        $collection = new Collection(array($resource));
        $collection->setCollectionName('resource');
        $collection->setCollectionRoute('hostname/resource');

        $this->plugin->setMetadataMap($metadata);

        $test = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $test);
        $this->assertArrayHasKey('_embedded', $test);
        $this->assertInternalType('array', $test['_embedded']);
        $this->assertArrayHasKey('resource', $test['_embedded']);
        $this->assertInternalType('array', $test['_embedded']['resource']);

        $resources = $test['_embedded']['resource'];
        $testResource = array_shift($resources);
        $this->assertInternalType('array', $testResource);
        $this->assertArrayHasKey('id', $testResource);
        $this->assertArrayHasKey('name', $testResource);
    }

    /**
     * @group 79
     */
    public function testInjectsLinksFromMetadataWhenCreatingResource()
    {
        $object = new TestAsset\Resource('foo', 'Foo');
        $resource = new Resource($object, 'foo');

        $metadata = new MetadataMap(array(
            'ZFTest\Hal\Plugin\TestAsset\Resource' => array(
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'links'      => array(
                    array(
                        'rel' => 'describedby',
                        'url' => 'http://example.com/api/help/resource',
                    ),
                    array(
                        'rel' => 'children',
                        'route' => array(
                            'name' => 'resource/children',
                        ),
                    ),
                ),
            ),
        ));

        $this->plugin->setMetadataMap($metadata);
        $resource = $this->plugin->createResourceFromMetadata($object, $metadata->get('ZFTest\Hal\Plugin\TestAsset\Resource'));
        $this->assertInstanceof('ZF\Hal\Resource', $resource);
        $links = $resource->getLinks();
        $this->assertTrue($links->has('describedby'));
        $this->assertTrue($links->has('children'));

        $describedby = $links->get('describedby');
        $this->assertTrue($describedby->hasUrl());
        $this->assertEquals('http://example.com/api/help/resource', $describedby->getUrl());

        $children = $links->get('children');
        $this->assertTrue($children->hasRoute());
        $this->assertEquals('resource/children', $children->getRoute());
    }

    /**
     * @group 79
     */
    public function testInjectsLinksFromMetadataWhenCreatingCollection()
    {
        $set = new TestAsset\Collection(
            array(
                (object) array('id' => 'foo', 'name' => 'foo'),
                (object) array('id' => 'bar', 'name' => 'bar'),
                (object) array('id' => 'baz', 'name' => 'baz'),
            )
        );

        $metadata = new MetadataMap(array(
            'ZFTest\Hal\Plugin\TestAsset\Collection' => array(
                'is_collection'       => true,
                'route_name'          => 'hostname/contacts',
                'resource_route_name' => 'hostname/embedded',
                'links'               => array(
                    array(
                        'rel' => 'describedby',
                        'url' => 'http://example.com/api/help/collection',
                    ),
                ),
            ),
        ));

        $this->plugin->setMetadataMap($metadata);

        $collection = $this->plugin->createCollectionFromMetadata(
            $set,
            $metadata->get('ZFTest\Hal\Plugin\TestAsset\Collection'
        ));
        $this->assertInstanceof('ZF\Hal\Collection', $collection);
        $links = $collection->getLinks();
        $this->assertTrue($links->has('describedby'));
        $link = $links->get('describedby');
        $this->assertTrue($link->hasUrl());
        $this->assertEquals('http://example.com/api/help/collection', $link->getUrl());
    }

    /**
     * @group 79
     */
    public function testRenderResourceTriggersEvent()
    {
        $resource = new Resource(
            (object) array(
                'id'   => 'user',
                'name' => 'matthew',
            ),
            'user'
        );
        $self = new Link('self');
        $self->setRoute('hostname/users', array('id' => 'user'));
        $resource->getLinks()->add($self);

        $this->plugin->getEventManager()->attach('renderResource', function ($e) {
            $resource = $e->getParam('resource');
            $resource->getLinks()->get('self')->setRouteParams(array('id' => 'matthew'));
        });

        $rendered = $this->plugin->renderResource($resource);
        $this->assertContains('/users/matthew', $rendered['_links']['self']['href']);
    }

    /**
     * @group 79
     */
    public function testRenderCollectionTriggersEvent()
    {
        $collection = new Collection(
            array(
                (object) array('id' => 'foo', 'name' => 'foo'),
                (object) array('id' => 'bar', 'name' => 'bar'),
                (object) array('id' => 'baz', 'name' => 'baz'),
            ),
            'hostname/contacts'
        );
        $self = new Link('self');
        $self->setRoute('hostname/contacts');
        $collection->getLinks()->add($self);
        $collection->setCollectionName('resources');

        $this->plugin->getEventManager()->attach('renderCollection', function ($e) {
            $collection = $e->getParam('collection');
            $collection->setAttributes(array('injected' => true));
        });

        $rendered = $this->plugin->renderCollection($collection);
        $this->assertArrayHasKey('injected', $rendered);
        $this->assertTrue($rendered['injected']);
    }

    public function matchUrl($url)
    {
        $url     = 'http://localhost.localdomain' . $url;
        $request = new Request();
        $request->setUri($url);

        $match = $this->router->match($request);
        if ($match instanceof RouteMatch) {
            $this->urlHelper->setRouteMatch($match);
        }

        return $match;
    }

    /**
     * @group 95
     */
    public function testPassingFalseReuseParamsOptionShouldOmitMatchedParametersInGeneratedLink()
    {
        $matches = $this->matchUrl('/resource/foo');
        $this->assertEquals('foo', $matches->getParam('id', false));

        $link = Link::factory(array(
            'rel' => 'resource',
            'route' => array(
                'name' => 'hostname/resource',
                'options' => array(
                    'reuse_matched_params' => false,
                ),
            ),
        ));
        $result = $this->plugin->fromLink($link);
        $expected = array(
            'href' => 'http://localhost.localdomain/resource',
        );
        $this->assertEquals($expected, $result);
    }

    public function testFromLinkShouldComposeAnyPropertiesInLink()
    {
        $link = Link::factory(array(
            'rel'   => 'resource',
            'url'   => 'http://api.example.com/foo?version=2',
            'props' => array(
                'version' => 2,
                'latest'  => true,
            ),
        ));
        $result = $this->plugin->fromLink($link);
        $expected = array(
            'href'    => 'http://api.example.com/foo?version=2',
            'version' => 2,
            'latest'  => true,
        );
        $this->assertEquals($expected, $result);
    }

    public function testCreateCollectionShouldUseCollectionRouteMetadataWhenInjectingSelfLink()
    {
        $collection = new Collection(array('foo' => 'bar'));
        $collection->setCollectionRoute('hostname/resource');
        $collection->setCollectionRouteOptions(array(
            'query' => array(
                'version' => 2,
            ),
        ));
        $result = $this->plugin->createCollection($collection);
        $links  = $result->getLinks();
        $self   = $links->get('self');
        $this->assertEquals(array(
            'query' => array(
                'version' => 2,
            ),
        ), $self->getRouteOptions());
    }

    public function testRenderingCollectionUsesCollectionNameFromMetadataMap()
    {
        $object1 = new TestAsset\Resource('foo', 'Foo');
        $object2 = new TestAsset\Resource('bar', 'Bar');
        $object3 = new TestAsset\Resource('baz', 'Baz');

        $collection = new TestAsset\Collection(array(
            $object1,
            $object2,
            $object3,
        ));

        $metadata = new MetadataMap(array(
            'ZFTest\Hal\Plugin\TestAsset\Resource' => array(
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ),
            'ZFTest\Hal\Plugin\TestAsset\Collection' => array(
                'is_collection'       => true,
                'collection_name'     => 'collection',
                'route_name'          => 'hostname/contacts',
                'resource_route_name' => 'hostname/embedded',
            ),
        ));

        $this->plugin->setMetadataMap($metadata);

        $halCollection = $this->plugin->createCollection($collection);
        $rendered = $this->plugin->renderCollection($halCollection);

        $this->assertRelationalLinkContains('/contacts', 'self', $rendered);
        $this->assertArrayHasKey('_embedded', $rendered);
        $this->assertInternalType('array', $rendered['_embedded']);
        $this->assertArrayHasKey('collection', $rendered['_embedded']);

        $renderedCollection = $rendered['_embedded']['collection'];

        foreach ($renderedCollection as $resource) {
            $this->assertRelationalLinkContains('/resource/', 'self', $resource);
        }
    }
}
