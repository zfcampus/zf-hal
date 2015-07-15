<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Plugin;

use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\Mvc\Router\Http\Segment;
use Zend\Mvc\MvcEvent;
use Zend\Paginator\Adapter\ArrayAdapter as ArrayPaginator;
use Zend\Paginator\Paginator;
use Zend\Uri\Http;
use Zend\Stdlib\Hydrator;
use Zend\View\Helper\Url as UrlHelper;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;
use ZF\Hal\Collection;
use ZF\Hal\Entity;
use ZF\Hal\Extractor\LinkCollectionExtractor;
use ZF\Hal\Extractor\LinkExtractor;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkCollection;
use ZF\Hal\Metadata\MetadataMap;
use ZF\Hal\Plugin\Hal as HalHelper;
use ZFTest\Hal\TestAsset as HalTestAsset;

/**
 * @subpackage UnitTest
 */
class HalTest extends TestCase
{
    /**
     * @var HalHelper
     */
    protected $plugin;

    public function setUp()
    {
        $this->router = $router = new TreeRouteStack();
        $route = new Segment('/resource[/[:id]]');
        $router->addRoute('resource', $route);
        $route2 = new Segment('/help');
        $router->addRoute('docs', $route2);
        $router->addRoute('hostname', [
            'type' => 'hostname',
            'options' => [
                'route' => 'localhost.localdomain',
            ],
            'child_routes' => [
                'resource' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/resource[/:id]'
                    ],
                    'may_terminate' => true,
                    'child_routes' => [
                        'children' => [
                            'type' => 'literal',
                            'options' => [
                                'route' => '/children',
                            ],
                        ],
                    ],
                ],
                'users' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/users[/:id]'
                    ]
                ],
                'contacts' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/contacts[/:id]'
                    ]
                ],
                'embedded' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/embedded[/:id]'
                    ]
                ],
                'embedded_custom' => [
                    'type' => 'segment',
                    'options' => [
                        'route' => '/embedded_custom[/:custom_id]'
                    ]
                ],
            ]
        ]);

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

        $linkExtractor = new LinkExtractor($serverUrlHelper, $urlHelper);
        $linkCollectionExtractor = new LinkCollectionExtractor($linkExtractor);
        $plugin->setLinkCollectionExtractor($linkCollectionExtractor);
    }

    public function assertRelationalLinkContains($match, $relation, $entity)
    {
        $this->assertInternalType('array', $entity);
        $this->assertArrayHasKey('_links', $entity);
        $links = $entity['_links'];
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

    public function testLinkCreationFromEntity()
    {
        $self = new Link('self');
        $self->setRoute('resource', ['id' => 123]);
        $docs = new Link('describedby');
        $docs->setRoute('docs');
        $entity = new Entity([], 123);
        $entity->getLinks()->add($self)->add($docs);
        $links = $this->plugin->fromResource($entity);

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

    public function testRendersEmbeddedCollectionsInsideEntities()
    {
        $collection = new Collection(
            [
                (object) ['id' => 'foo', 'name' => 'foo'],
                (object) ['id' => 'bar', 'name' => 'bar'],
                (object) ['id' => 'baz', 'name' => 'baz'],
            ],
            'hostname/contacts'
        );
        $entity = new Entity(
            (object) [
                'id'       => 'user',
                'contacts' => $collection,
            ],
            'user'
        );
        $self = new Link('self');
        $self->setRoute('hostname/users', ['id' => 'user']);
        $entity->getLinks()->add($self);

        $rendered = $this->plugin->renderEntity($entity);
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

    public function testRendersEmbeddedEntitiesInsideEntitiesBasedOnMetadataMap()
    {
        $object = new TestAsset\Entity('foo', 'Foo');
        $object->first_child  = new TestAsset\EmbeddedEntity('bar', 'Bar');
        $object->second_child = new TestAsset\EmbeddedEntityWithCustomIdentifier('baz', 'Baz');
        $entity = new Entity($object, 'foo');
        $self = new Link('self');
        $self->setRoute('hostname/resource', ['id' => 'foo']);
        $entity->getLinks()->add($self);

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedEntity' => [
                'hydrator' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedEntityWithCustomIdentifier' => [
                'hydrator'        => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'           => 'hostname/embedded_custom',
                'route_identifier_name' => 'custom_id',
                'entity_identifier_name' => 'custom_id',
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $rendered = $this->plugin->renderEntity($entity);
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
        $object = new TestAsset\Entity('foo', 'Foo');
        $object->first_child  = new TestAsset\EmbeddedProxyEntity('bar', 'Bar');
        $object->second_child = new TestAsset\EmbeddedProxyEntityWithCustomIdentifier('baz', 'Baz');
        $entity = new Entity($object, 'foo');
        $self = new Link('self');
        $self->setRoute('hostname/resource', ['id' => 'foo']);
        $entity->getLinks()->add($self);

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedEntity' => [
                'hydrator' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedEntityWithCustomIdentifier' => [
                'hydrator'        => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'           => 'hostname/embedded_custom',
                'route_identifier_name' => 'custom_id',
                'entity_identifier_name' => 'custom_id',
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $rendered = $this->plugin->renderEntity($entity);
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

    public function testRendersJsonSerializableObjectUsingJsonserializeMethod()
    {
        $object   = new TestAsset\JsonSerializableEntity('foo', 'Foo');
        $entity   = new Entity($object, 'foo');
        $rendered = $this->plugin->renderEntity($entity);
        $this->assertArrayHasKey('id', $rendered);
        $this->assertArrayNotHasKey('name', $rendered);
        $this->assertArrayHasKey('_links', $rendered);
    }

    public function testRendersEmbeddedCollectionsInsideEntitiesBasedOnMetadataMap()
    {
        $collection = new TestAsset\Collection([
            (object) ['id' => 'foo', 'name' => 'foo'],
            (object) ['id' => 'bar', 'name' => 'bar'],
            (object) ['id' => 'baz', 'name' => 'baz'],
        ]);

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Collection' => [
                'is_collection'       => true,
                'collection_name'     => 'collection', // should be overridden
                'route_name'          => 'hostname/contacts',
                'entity_route_name'   => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $entity = new Entity(
            (object) [
                'id'       => 'user',
                'contacts' => $collection,
            ],
            'user'
        );
        $self = new Link('self');
        $self->setRoute('hostname/users', ['id' => 'user']);
        $entity->getLinks()->add($self);

        $rendered = $this->plugin->renderEntity($entity);

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
        $childCollection = new TestAsset\Collection([
            (object) ['id' => 'foo', 'name' => 'foo'],
            (object) ['id' => 'bar', 'name' => 'bar'],
            (object) ['id' => 'baz', 'name' => 'baz'],
        ]);
        $entity = new TestAsset\Entity('spock', 'Spock');
        $entity->first_child = $childCollection;

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Collection' => [
                'is_collection'  => true,
                'route'          => 'hostname/contacts',
                'entity_route'   => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $collection = new Collection([$entity], 'hostname/resource');
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

    // @codingStandardsIgnoreStart
    public function testDoesNotRenderEmbeddedEntitiesInsideCollectionsBasedOnMetadataMapAndRenderEmbeddedEntitiesAsFalse()
    {
        $entity = new TestAsset\Entity('spock', 'Spock');
        $entity->first_child  = new TestAsset\EmbeddedEntity('bar', 'Bar');
        $entity->second_child = new TestAsset\EmbeddedEntityWithCustomIdentifier('baz', 'Baz');

        $metadata = new MetadataMap(array(
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedEntity' => array(
                'hydrator' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
            ),
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedEntityWithCustomIdentifier' => array(
                'hydrator'        => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'           => 'hostname/embedded_custom',
                'route_identifier_name' => 'custom_id',
                'entity_identifier_name' => 'custom_id',
            ),

            'ZFTest\Hal\Plugin\TestAsset\Collection' => array(
                'is_collection'  => true,
                'route'          => 'hostname/contacts',
                'entity_route'   => 'hostname/embedded',
            ),
            'ZFTest\Hal\Plugin\TestAsset\Entity' => array(
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
            ),
        ));

        $this->plugin->setMetadataMap($metadata);
        $this->plugin->setRenderEmbeddedEntities(false);

        $collection = new Collection(array($entity), 'hostname/resource');
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
    // @codingStandardsIgnoreEnd

    public function testWillNotAllowInjectingASelfRelationMultipleTimes()
    {
        $entity = new Entity([
            'id'  => 1,
            'foo' => 'bar',
        ], 1);
        $links = $entity->getLinks();

        $this->assertFalse($links->has('self'));

        $this->plugin->injectSelfLink($entity, 'hostname/resource');

        $this->assertTrue($links->has('self'));
        $link = $links->get('self');
        $this->assertInstanceof('ZF\Hal\Link\Link', $link);

        $this->plugin->injectSelfLink($entity, 'hostname/resource');
        $this->assertTrue($links->has('self'));
        $link = $links->get('self');
        $this->assertInstanceof('ZF\Hal\Link\Link', $link);
    }

    public function testEntityPropertiesCanBeLinks()
    {
        $embeddedLink = new Link('embeddedLink');
        $embeddedLink->setRoute('hostname/contacts', ['id' => 'bar']);

        $properties = [
            'id' => '10',
            'embeddedLink' => $embeddedLink,
        ];

        $entity = new Entity((object) $properties, 'foo');

        $rendered = $this->plugin->renderEntity($entity);

        $this->assertArrayHasKey('_links', $rendered);
        $this->assertArrayHasKey('embeddedLink', $rendered['_links']);
        $this->assertArrayNotHasKey('embeddedLink', $rendered);
        $this->assertArrayHasKey('href', $rendered['_links']['embeddedLink']);
        $this->assertEquals('http://localhost.localdomain/contacts/bar', $rendered['_links']['embeddedLink']['href']);
    }

    public function testEntityPropertyLinksUseHref()
    {
        $link1 = new Link('link1');
        $link1->setUrl('link1');

        $link2 = new Link('link2');
        $link2->setUrl('link2');

        $properties = [
            'id' => '10',
            'bar' => $link1,
            'baz' => $link2,
        ];

        $entity = new Entity((object) $properties, 'foo');

        $rendered = $this->plugin->renderEntity($entity);

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
        $link->setRoute('hostname/contacts', ['id' => 'bar']);

        //simple link
        $collection = new LinkCollection();
        $collection->add($link);

        //array of links
        $linkArray = new Link('arrayLink');
        $linkArray->setRoute('hostname/contacts', ['id' => 'bar']);
        $collection->add($linkArray);

        $linkArray = new Link('arrayLink');
        $linkArray->setRoute('hostname/contacts', ['id' => 'baz']);
        $collection->add($linkArray);

        $properties = [
            'id' => '10',
            'links' => $collection,
        ];

        $entity = new Entity((object) $properties, 'foo');

        $rendered = $this->plugin->renderEntity($entity);

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
    public function testRenderingEmbeddedEntityEmbedsEntity()
    {
        $embedded = new Entity((object) ['id' => 'foo', 'name' => 'foo'], 'foo');
        $self = new Link('self');
        $self->setRoute('hostname/contacts', ['id' => 'foo']);
        $embedded->getLinks()->add($self);

        $entity = new Entity((object) ['id' => 'user', 'contact' => $embedded], 'user');
        $self = new Link('self');
        $self->setRoute('hostname/users', ['id' => 'user']);
        $entity->getLinks()->add($self);

        $rendered = $this->plugin->renderEntity($entity);

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
    public function testRenderingCollectionRendersAllLinksInEmbeddedEntities()
    {
        $embedded = new Entity((object) ['id' => 'foo', 'name' => 'foo'], 'foo');
        $links = $embedded->getLinks();
        $self = new Link('self');
        $self->setRoute('hostname/users', ['id' => 'foo']);
        $links->add($self);
        $phones = new Link('phones');
        $phones->setUrl('http://localhost.localdomain/users/foo/phones');
        $links->add($phones);

        $collection = new Collection([$embedded]);
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

    public function testEntitiesFromCollectionCanUseHydratorSetInMetadataMap()
    {
        $object   = new TestAsset\EntityWithProtectedProperties('foo', 'Foo');
        $entity   = new Entity($object, 'foo');

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\EntityWithProtectedProperties' => [
                'hydrator'   => 'ArraySerializable',
                'route_name' => 'hostname/resource',
            ],
        ]);

        $collection = new Collection([$entity]);
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
    public function testInjectsLinksFromMetadataWhenCreatingEntity()
    {
        $object = new TestAsset\Entity('foo', 'Foo');
        $entity = new Entity($object, 'foo');

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'links'      => [
                    [
                        'rel' => 'describedby',
                        'url' => 'http://example.com/api/help/resource',
                    ],
                    [
                        'rel' => 'children',
                        'route' => [
                            'name' => 'resource/children',
                        ],
                    ],
                ],
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);
        $entity = $this->plugin->createEntityFromMetadata(
            $object,
            $metadata->get('ZFTest\Hal\Plugin\TestAsset\Entity')
        );
        $this->assertInstanceof('ZF\Hal\Entity', $entity);
        $links = $entity->getLinks();
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
     * @group 47
     */
    public function testRetainsLinksInjectedViaMetadataDuringCreateEntity()
    {
        $object = new TestAsset\Entity('foo', 'Foo');
        $entity = new Entity($object, 'foo');

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'links'      => [
                    [
                        'rel' => 'describedby',
                        'url' => 'http://example.com/api/help/resource',
                    ],
                    [
                        'rel' => 'children',
                        'route' => [
                            'name' => 'resource/children',
                        ],
                    ],
                ],
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);
        $entity = $this->plugin->createEntity($object, 'hostname/resource', 'id');
        $this->assertInstanceof('ZF\Hal\Entity', $entity);
        $links = $entity->getLinks();
        $this->assertTrue($links->has('describedby'), 'Missing describedby link');
        $this->assertTrue($links->has('children'), 'Missing children link');

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
            [
                (object) ['id' => 'foo', 'name' => 'foo'],
                (object) ['id' => 'bar', 'name' => 'bar'],
                (object) ['id' => 'baz', 'name' => 'baz'],
            ]
        );

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Collection' => [
                'is_collection'       => true,
                'route_name'          => 'hostname/contacts',
                'entity_route_name'   => 'hostname/embedded',
                'links'               => [
                    [
                        'rel' => 'describedby',
                        'url' => 'http://example.com/api/help/collection',
                    ],
                ],
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $collection = $this->plugin->createCollectionFromMetadata(
            $set,
            $metadata->get('ZFTest\Hal\Plugin\TestAsset\Collection')
        );
        $this->assertInstanceof('ZF\Hal\Collection', $collection);
        $links = $collection->getLinks();
        $this->assertTrue($links->has('describedby'));
        $link = $links->get('describedby');
        $this->assertTrue($link->hasUrl());
        $this->assertEquals('http://example.com/api/help/collection', $link->getUrl());
    }

    /**
     * Test that the hal metadata route params config allows callables.
     *
     * All callables should be passed the object being used for entity creation.
     * If closure binding is supported, any closures should be bound to that
     * object.
     *
     * The return value should be used as the route param for the link (in
     * place of the callable).
     */
    public function testRouteParamsAllowsCallable()
    {
        $object = new TestAsset\Entity('foo', 'Foo');

        $callback = $this->getMock('stdClass', ['callback']);
        $callback->expects($this->atLeastOnce())
                 ->method('callback')
                 ->with($this->equalTo($object))
                 ->will($this->returnValue('callback-param'));

        $test = $this;

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'     => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name'   => 'hostname/resource',
                'route_params' => [
                    'test-1' => [$callback, 'callback'],
                    'test-2' => function ($expected) use ($object, $test) {
                        $test->assertSame($expected, $object);
                        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                            $test->assertSame($object, $this);
                        }

                        return 'closure-param';
                    },
                ],
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);
        $entity = $this->plugin->createEntityFromMetadata(
            $object,
            $metadata->get('ZFTest\Hal\Plugin\TestAsset\Entity')
        );
        $this->assertInstanceof('ZF\Hal\Entity', $entity);

        $links = $entity->getLinks();
        $this->assertTrue($links->has('self'));

        $self = $links->get('self');
        $params = $self->getRouteParams();

        $this->assertArrayHasKey('test-1', $params);
        $this->assertEquals('callback-param', $params['test-1']);

        $this->assertArrayHasKey('test-2', $params);
        $this->assertEquals('closure-param', $params['test-2']);
    }

    /**
     * @group 79
     */
    public function testRenderEntityTriggersEvents()
    {
        $entity = new Entity(
            (object) [
                'id'   => 'user',
                'name' => 'matthew',
            ],
            'user'
        );
        $self = new Link('self');
        $self->setRoute('hostname/users', ['id' => 'user']);
        $entity->getLinks()->add($self);

        $this->plugin->getEventManager()->attach('renderEntity', function ($e) {
            $entity = $e->getParam('entity');
            $entity->getLinks()->get('self')->setRouteParams(['id' => 'matthew']);
        });

        $rendered = $this->plugin->renderEntity($entity);
        $this->assertContains('/users/matthew', $rendered['_links']['self']['href']);
    }

    /**
     * @group 79
     */
    public function testRenderCollectionTriggersEvents()
    {
        $collection = new Collection(
            [
                (object) ['id' => 'foo', 'name' => 'foo'],
                (object) ['id' => 'bar', 'name' => 'bar'],
                (object) ['id' => 'baz', 'name' => 'baz'],
            ],
            'hostname/contacts'
        );
        $self = new Link('self');
        $self->setRoute('hostname/contacts');
        $collection->getLinks()->add($self);
        $collection->setCollectionName('resources');

        $this->plugin->getEventManager()->attach('renderCollection', function ($e) {
            $collection = $e->getParam('collection');
            $collection->setAttributes(['injected' => true]);
        });

        $rendered = $this->plugin->renderCollection($collection);
        $this->assertArrayHasKey('injected', $rendered);
        $this->assertTrue($rendered['injected']);

        $that = $this;
        $this->plugin->getEventManager()->attach('renderCollection.post', function ($e) use ($that) {
            $collection = $e->getParam('collection');
            $payload = $e->getParam('payload');

            $that->assertInstanceOf('ArrayObject', $payload);
            $that->assertInstanceOf('ZF\Hal\Collection', $collection);

            $payload['_post'] = true;
        });

        $rendered = $this->plugin->renderCollection($collection);
        $this->assertArrayHasKey('_post', $rendered);
        $this->assertTrue($rendered['_post']);
    }

    public function testFromLinkShouldUseLinkExtractor()
    {
        $extraction = true;

        $linkExtractor = $this->getMockBuilder('ZF\Hal\Extractor\LinkExtractor')
            ->disableOriginalConstructor()
            ->getMock();
        $linkExtractor
            ->expects($this->once())
            ->method('extract')
            ->will($this->returnValue($extraction));

        $linkCollectionExtractor = $this->getMockBuilder('ZF\Hal\Extractor\LinkCollectionExtractor')
            ->disableOriginalConstructor()
            ->getMock();
        $linkCollectionExtractor
            ->expects($this->once())
            ->method('getLinkExtractor')
            ->will($this->returnValue($linkExtractor));

        $this->plugin->setLinkCollectionExtractor($linkCollectionExtractor);

        $link = new Link('foo');

        $result = $this->plugin->fromLink($link);

        $this->assertEquals($extraction, $result);
    }

    public function testFromLinkCollectionShouldUseLinkCollectionExtractor()
    {
        $extraction = true;

        $linkCollectionExtractor = $this->getMockBuilder('ZF\Hal\Extractor\LinkCollectionExtractor')
            ->disableOriginalConstructor()
            ->getMock();
        $linkCollectionExtractor
            ->expects($this->once())
            ->method('extract')
            ->will($this->returnValue($extraction));

        $this->plugin->setLinkCollectionExtractor($linkCollectionExtractor);

        $linkCollection = new LinkCollection();

        $result = $this->plugin->fromLinkCollection($linkCollection);

        $this->assertEquals($extraction, $result);
    }

    public function testCreateCollectionShouldUseCollectionRouteMetadataWhenInjectingSelfLink()
    {
        $collection = new Collection(['foo' => 'bar']);
        $collection->setCollectionRoute('hostname/resource');
        $collection->setCollectionRouteOptions([
            'query' => [
                'version' => 2,
            ],
        ]);
        $result = $this->plugin->createCollection($collection);
        $links  = $result->getLinks();
        $self   = $links->get('self');
        $this->assertEquals([
            'query' => [
                'version' => 2,
            ],
        ], $self->getRouteOptions());
    }

    public function testRenderingCollectionUsesCollectionNameFromMetadataMap()
    {
        $object1 = new TestAsset\Entity('foo', 'Foo');
        $object2 = new TestAsset\Entity('bar', 'Bar');
        $object3 = new TestAsset\Entity('baz', 'Baz');

        $collection = new TestAsset\Collection([
            $object1,
            $object2,
            $object3,
        ]);

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\Hal\Plugin\TestAsset\Collection' => [
                'is_collection'       => true,
                'collection_name'     => 'collection',
                'route_name'          => 'hostname/contacts',
                'entity_route_name'   => 'hostname/embedded',
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $halCollection = $this->plugin->createCollection($collection);
        $rendered = $this->plugin->renderCollection($halCollection);

        $this->assertRelationalLinkContains('/contacts', 'self', $rendered);
        $this->assertArrayHasKey('_embedded', $rendered);
        $this->assertInternalType('array', $rendered['_embedded']);
        $this->assertArrayHasKey('collection', $rendered['_embedded']);

        $renderedCollection = $rendered['_embedded']['collection'];

        foreach ($renderedCollection as $entity) {
            $this->assertRelationalLinkContains('/resource/', 'self', $entity);
        }
    }

    /**
     * @group 14
     */
    public function testRenderingPaginatorCollectionRendersPaginationAttributes()
    {
        $set = [];
        for ($id = 1; $id <= 100; $id += 1) {
            $entity = new Entity((object) ['id' => $id, 'name' => 'foo'], 'foo');
            $links = $entity->getLinks();
            $self = new Link('self');
            $self->setRoute('hostname/users', ['id' => $id]);
            $links->add($self);
            $set[] = $entity;
        }

        $paginator  = new Paginator(new ArrayPaginator($set));
        $collection = new Collection($paginator);
        $collection->setCollectionName('users');
        $collection->setCollectionRoute('hostname/users');
        $collection->setPage(3);
        $collection->setPageSize(10);

        $rendered = $this->plugin->renderCollection($collection);
        $expected = [
            '_links',
            '_embedded',
            'page_count',
            'page_size',
            'total_items',
            'page',
        ];
        $this->assertEquals($expected, array_keys($rendered));
        $this->assertEquals(100, $rendered['total_items']);
        $this->assertEquals(3, $rendered['page']);
        $this->assertEquals(10, $rendered['page_count']);
        $this->assertEquals(10, $rendered['page_size']);
        return $rendered;
    }

    /**
     * @group 50
     * @depends testRenderingPaginatorCollectionRendersPaginationAttributes
     */
    public function testRenderingPaginatorCollectionRendersFirstLinkWithoutPageInQueryString($rendered)
    {
        $links = $rendered['_links'];
        $this->assertArrayHasKey('first', $links);
        $first = $links['first'];
        $this->assertArrayHasKey('href', $first);
        $this->assertNotContains('page=1', $first['href']);
    }

    /**
     * @group 14
     */
    public function testRenderingNonPaginatorCollectionRendersCountOfTotalItems()
    {
        $embedded = new Entity((object) ['id' => 'foo', 'name' => 'foo'], 'foo');
        $links = $embedded->getLinks();
        $self = new Link('self');
        $self->setRoute('hostname/users', ['id' => 'foo']);
        $links->add($self);

        $collection = new Collection([$embedded]);
        $collection->setCollectionName('users');
        $self = new Link('self');
        $self->setRoute('hostname/users');
        $collection->getLinks()->add($self);

        $rendered = $this->plugin->renderCollection($collection);

        $expectedKeys = ['_links', '_embedded', 'total_items'];
        $this->assertEquals($expectedKeys, array_keys($rendered));
    }

    /**
     * @group 33
     */
    public function testCreateEntityShouldNotSerializeEntity()
    {
        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
        ]);
        $this->plugin->setMetadataMap($metadata);

        $foo = new TestAsset\Entity('foo', 'Foo Bar');

        $entity = $this->plugin->createEntity($foo, 'api.foo', 'foo_id');
        $this->assertInstanceOf('ZF\Hal\Entity', $entity);
        $this->assertSame($foo, $entity->entity);
    }

    /**
     * Test that the convertEntityToArray() caches serialization results by object.
     *
     * This is done because if you call createEntity() -- say, from a ZF\Rest\RestController,
     * you may end up calling convertEntityToArray() twice -- once to create the HAL
     * entity with the appropriate identifier, and another when creating the serialized
     * representation.
     *
     * This method is testing internals of the plugin; realistically, the behavior is
     * transparent to the end-user.
     *
     * @group 33
     */
    public function testConvertEntityToArrayCachesSerialization()
    {
        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
        ]);
        $this->plugin->setMetadataMap($metadata);

        $foo = new TestAsset\Entity('foo', 'Foo Bar');

        $entity1 = $this->plugin->createEntityFromMetadata($foo, $metadata->get($foo));
        $serialized1 = $this->plugin->renderEntity($entity1);

        $entity2 = $this->plugin->createEntityFromMetadata($foo, $metadata->get($foo));
        $serialized2 = $this->plugin->renderEntity($entity2);

        $this->assertSame($serialized1, $serialized2);

        $data = $serialized1;
        unset($data['_links']);

        $r = new ReflectionObject($this->plugin);
        $p = $r->getProperty('serializedEntities');
        $p->setAccessible(true);
        $serializedEntities = $p->getValue($this->plugin);
        $this->assertInstanceOf('SplObjectStorage', $serializedEntities);
        $this->assertTrue($serializedEntities->contains($foo));
        $this->assertSame($data, $serializedEntities[$foo]);
    }

    /**
     * @group 91
     */
    public function testConvertEntityToArrayOnlyConvertsPublicProperties()
    {
        $foo = new TestAsset\Entity('foo', 'Foo Bar');
        $entity = $this->plugin->createEntity($foo, 'resource', 'foo_id');
        $data = $this->plugin->renderEntity($entity);

        $this->assertFalse(array_key_exists('doNotExportMe', $data));
    }

    /**
     * @group 39
     */
    public function testCreateEntityPassesNullValueForIdentifierIfNotDiscovered()
    {
        $entity = ['foo' => 'bar'];
        $hal    = $this->plugin->createEntity($entity, 'api.foo', 'foo_id');
        $this->assertInstanceOf('ZF\Hal\Entity', $hal);
        $this->assertEquals($entity, $hal->entity);
        $this->assertNull($hal->id);

        $links = $hal->getLinks();
        $this->assertTrue($links->has('self'));
        $link = $links->get('self');
        $params = $link->getRouteParams();
        $this->assertEquals([], $params);
    }

    public function testAddHydratorDoesntFailWithAutoInvokables()
    {
        $this->plugin->addHydrator('stdClass', 'ZFTest\Hal\Plugin\TestAsset\DummyHydrator');

        $this->assertInstanceOf(
            'ZFTest\Hal\Plugin\TestAsset\DummyHydrator',
            $this->plugin->getHydratorForEntity(new \stdClass)
        );
    }

    /**
     * @param Entity      $entity
     * @param MetadataMap $metadataMap
     * @param array       $expectedResult
     * @param array       $exception
     *
     * @dataProvider renderEntityMaxDepthProvider
     */
    public function testRenderEntityMaxDepth($entity, $metadataMap, $expectedResult, $exception = null)
    {
        $this->plugin->setMetadataMap($metadataMap);

        if ($exception) {
            $this->setExpectedException($exception['class'], $exception['message']);
        }

        $result = $this->plugin->renderEntity($entity);

        $this->assertEquals($expectedResult, $result);
    }

    public function renderEntityMaxDepthProvider()
    {
        return [
            /**
             * array(
             *     $entity,
             *     $metadataMap,
             *     $expectedResult,
             *     $exception,
             * )
             */
            [
                $this->createNestedEntity(),
                $this->createNestedMetadataMap(),
                null,
                [
                    'class'   => 'ZF\Hal\Exception\CircularReferenceException',
                    'message' => 'Circular reference detected in \'ZFTest\Hal\Plugin\TestAsset\Entity\'',
                ]
            ],
            [
                $this->createNestedEntity(),
                $this->createNestedMetadataMap(1),
                [
                    'id' => 'foo',
                    'name' => 'Foo',
                    'second_child' => null,
                    '_embedded' => [
                        'first_child' => [
                            'id' => 'bar',
                            '_embedded' => [
                                'parent' => [
                                    '_links' => [
                                        'self' => [
                                            'href' => 'http://localhost.localdomain/resource/foo'
                                        ],
                                    ],
                                ]
                            ],
                            '_links' => [
                                'self' => [
                                    'href' => 'http://localhost.localdomain/embedded/bar'
                                ],
                            ],
                        ],
                    ],
                    '_links' => [
                        'self' => [
                            'href' => 'http://localhost.localdomain/resource/foo'
                        ],
                    ],
                ]
            ],
            [
                $this->createNestedEntity(),
                $this->createNestedMetadataMap(2),
                [
                    'id' => 'foo',
                    'name' => 'Foo',
                    'second_child' => null,
                    '_embedded' => [
                        'first_child' => [
                            'id' => 'bar',
                            '_embedded' => [
                                'parent' => [
                                    'id' => 'foo',
                                    'name' => 'Foo',
                                    'second_child' => null,
                                    '_embedded' => [
                                        'first_child' => [
                                            '_links' => [
                                                'self' => [
                                                    'href' => 'http://localhost.localdomain/embedded/bar'
                                                ],
                                            ],
                                        ],
                                    ],
                                    '_links' => [
                                        'self' => [
                                            'href' => 'http://localhost.localdomain/resource/foo'
                                        ],
                                    ],
                                ]
                            ],
                            '_links' => [
                                'self' => [
                                    'href' => 'http://localhost.localdomain/embedded/bar'
                                ],
                            ],
                        ],
                    ],
                    '_links' => [
                        'self' => [
                            'href' => 'http://localhost.localdomain/resource/foo'
                        ],
                    ],
                ]
            ]
        ];
    }

    protected function createNestedEntity()
    {
        $object = new TestAsset\Entity('foo', 'Foo');
        $object->first_child  = new TestAsset\EmbeddedEntityWithBackReference('bar', $object);
        $entity = new Entity($object, 'foo');
        $self = new Link('self');
        $self->setRoute('hostname/resource', ['id' => 'foo']);
        $entity->getLinks()->add($self);

        return $entity;
    }

    protected function createNestedMetadataMap($maxDepth = null)
    {
        return new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
                'max_depth' => $maxDepth,
            ],
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedEntityWithBackReference' => [
                'hydrator' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
        ]);
    }

    public function testSubsequentRenderEntityCalls()
    {
        $entity = $this->createNestedEntity();
        $metadataMap1 = $this->createNestedMetadataMap(0);
        $metadataMap2 = $this->createNestedMetadataMap(1);

        $this->plugin->setMetadataMap($metadataMap1);
        $result1 = $this->plugin->renderEntity($entity);

        $this->plugin->setMetadataMap($metadataMap2);
        $result2 = $this->plugin->renderEntity($entity);

        $this->assertNotEquals($result1, $result2);
    }

    /**
     * @param $collection
     * @param $metadataMap
     * @param $expectedResult
     * @param $exception
     *
     * @dataProvider renderCollectionWithMaxDepthProvider
     */
    public function testRenderCollectionWithMaxDepth($collection, $metadataMap, $expectedResult, $exception = null)
    {
        $this->plugin->setMetadataMap($metadataMap);

        if ($exception) {
            $this->setExpectedException($exception['class'], $exception['message']);
        }

        if (is_callable($collection)) {
            $collection = $collection();
        }

        $halCollection = $this->plugin->createCollection($collection);
        $result = $this->plugin->renderCollection($halCollection);

        $this->assertEquals($expectedResult, $result);
    }

    public function renderCollectionWithMaxDepthProvider()
    {
        return [
            [
                function () {
                    $object1 = new TestAsset\Entity('foo', 'Foo');
                    $object1->first_child  = new TestAsset\EmbeddedEntityWithBackReference('bar', $object1);
                    $object2 = new TestAsset\Entity('bar', 'Bar');
                    $object3 = new TestAsset\Entity('baz', 'Baz');

                    $collection = new TestAsset\Collection([
                        $object1,
                        $object2,
                        $object3
                    ]);

                    return $collection;
                },
                $this->createNestedCollectionMetadataMap(),
                null,
                [
                    'class'   => 'ZF\Hal\Exception\CircularReferenceException',
                    'message' => 'Circular reference detected in \'ZFTest\Hal\Plugin\TestAsset\Entity\'',
                ]
            ],
            [
                function () {
                    $object1 = new TestAsset\Entity('foo', 'Foo');
                    $object1->first_child  = new TestAsset\EmbeddedEntityWithBackReference('bar', $object1);
                    $object2 = new TestAsset\Entity('bar', 'Bar');
                    $object3 = new TestAsset\Entity('baz', 'Baz');

                    $collection = new TestAsset\Collection([
                        $object1,
                        $object2,
                        $object3
                    ]);

                    return $collection;
                },
                $this->createNestedCollectionMetadataMap(1),
                [
                    '_links' => [
                        'self' => [
                            'href' => 'http://localhost.localdomain/contacts',
                        ],
                    ],
                    '_embedded' => [
                        'collection' => [
                            [
                                'id'           => 'foo',
                                'name'         => 'Foo',
                                'second_child' => null,
                                '_embedded'    => [
                                    'first_child' => [
                                        'id'        => 'bar',
                                        '_embedded' => [
                                            'parent' => [
                                                '_links' => [
                                                    'self' => [
                                                        'href' => 'http://localhost.localdomain/resource/foo',
                                                    ],
                                                ],
                                            ],
                                        ],
                                        '_links'    => [
                                            'self' => [
                                                'href' => 'http://localhost.localdomain/embedded/bar',
                                            ],
                                        ],
                                    ],
                                ],
                                '_links'       => [
                                    'self' => [
                                        'href' => 'http://localhost.localdomain/resource/foo',
                                    ],
                                ],
                            ],
                            [
                                'id'           => 'bar',
                                'name'         => 'Bar',
                                'first_child'  => null,
                                'second_child' => null,
                                '_links'       => [
                                    'self' => [
                                        'href' => 'http://localhost.localdomain/resource/bar',
                                    ],
                                ],
                            ],
                            [
                                'id'           => 'baz',
                                'name'         => 'Baz',
                                'first_child'  => null,
                                'second_child' => null,
                                '_links'       => [
                                    'self' => [
                                        'href' => 'http://localhost.localdomain/resource/baz',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'total_items' => 3,
                ],
            ],
            [
                function () {
                    $object1 = new TestAsset\Entity('foo', 'Foo');
                    $object2 = new TestAsset\Entity('bar', 'Bar');

                    $collection = new TestAsset\Collection([
                        $object1,
                        $object2,
                    ]);
                    $object1->first_child = $collection;

                    return $collection;
                },
                $this->createNestedCollectionMetadataMap(),
                null,
                [
                    'class'   => 'ZF\Hal\Exception\CircularReferenceException',
                    'message' => 'Circular reference detected in \'ZFTest\Hal\Plugin\TestAsset\Entity\'',
                ]
            ],
            [
                function () {
                    $object1 = new TestAsset\Entity('foo', 'Foo');
                    $object2 = new TestAsset\Entity('bar', 'Bar');

                    $collection = new TestAsset\Collection([
                        $object1,
                        $object2,
                    ]);
                    $object1->first_child = $collection;

                    return $collection;
                },
                $this->createNestedCollectionMetadataMap(1),
                [
                    '_links' => [
                        'self' => [
                            'href' => 'http://localhost.localdomain/contacts',
                        ],
                    ],
                    '_embedded' => [
                        'collection' => [
                            [
                                'id'           => 'foo',
                                'name'         => 'Foo',
                                'second_child' => null,
                                '_embedded'    => [
                                    'first_child' => [
                                        [
                                            '_links' => [
                                                'self' => [
                                                    'href' => 'http://localhost.localdomain/resource/foo',
                                                ],
                                            ],
                                        ],
                                        [
                                            '_links' => [
                                                'self' => [
                                                    'href' => 'http://localhost.localdomain/resource/bar',
                                                ],
                                            ],
                                        ]
                                    ],
                                ],
                                '_links'       => [
                                    'self' => [
                                        'href' => 'http://localhost.localdomain/resource/foo',
                                    ],
                                ],
                            ],
                            [
                                'id'           => 'bar',
                                'name'         => 'Bar',
                                'first_child'  => null,
                                'second_child' => null,
                                '_links'       => [
                                    'self' => [
                                        'href' => 'http://localhost.localdomain/resource/bar',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'total_items' => 2,
                ],
            ]
        ];
    }

    protected function createNestedCollectionMetadataMap($maxDepth = null)
    {
        return new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Collection' => [
                'is_collection'       => true,
                'collection_name'     => 'collection',
                'route_name'          => 'hostname/contacts',
                'entity_route_name'   => 'hostname/embedded',
                'max_depth'           => $maxDepth,
            ],
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'   => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name' => 'hostname/resource',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
            'ZFTest\Hal\Plugin\TestAsset\EmbeddedEntityWithBackReference' => [
                'hydrator' => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route'    => 'hostname/embedded',
                'route_identifier_name' => 'id',
                'entity_identifier_name' => 'id',
            ],
        ]);
    }

    /**
     * @group 102
     */
    public function testRenderingEntityTwiceMustNotDuplicateLinkProperties()
    {
        $link = new Link('resource');
        $link->setRoute('resource', ['id' => 'user']);

        $entity = new Entity(
            (object) [
                'id'   => 'user',
                'name' => 'matthew',
                'resource' => $link,
            ],
            'user'
        );

        $rendered1 = $this->plugin->renderEntity($entity);
        $rendered2 = $this->plugin->renderEntity($entity);
        $this->assertEquals($rendered1, $rendered2);
    }

    /**
     * @group 102
     */
    public function testRenderingEntityTwiceMustNotDuplicateLinkCollectionProperties()
    {
        $link = new Link('resource');
        $link->setRoute('resource', ['id' => 'user']);
        $links = new LinkCollection();
        $links->add($link);

        $entity = new Entity(
            (object) [
                'id'   => 'user',
                'name' => 'matthew',
                'resources' => $links,
            ],
            'user'
        );

        $rendered1 = $this->plugin->renderEntity($entity);
        $rendered2 = $this->plugin->renderEntity($entity);
        $this->assertEquals($rendered1, $rendered2);
    }

    public function testCreateEntityFromMetadataWithoutForcedSelfLinks()
    {
        $object = new TestAsset\Entity('foo', 'Foo');
        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'         => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name'       => 'hostname/resource',
                'links'            => [],
                'force_self_link' => false,
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);
        $entity = $this->plugin->createEntityFromMetadata(
            $object,
            $metadata->get('ZFTest\Hal\Plugin\TestAsset\Entity')
        );
        $links = $entity->getLinks();
        $this->assertFalse($links->has('self'));
    }

    public function testCreateEntityWithoutForcedSelfLinks()
    {
        $object = new TestAsset\Entity('foo', 'Foo');

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Entity' => [
                'hydrator'        => 'Zend\Stdlib\Hydrator\ObjectProperty',
                'route_name'      => 'hostname/resource',
                'links'           => [],
                'force_self_link' => false,
            ],
        ]);
        $this->plugin->setMetadataMap($metadata);
        $entity = $this->plugin->createEntity($object, 'hostname/resource', 'id');
        $links = $entity->getLinks();
        $this->assertFalse($links->has('self'));
    }

    public function testCreateCollectionFromMetadataWithoutForcedSelfLinks()
    {
        $set = new TestAsset\Collection(
            [
                (object) ['id' => 'foo', 'name' => 'foo'],
                (object) ['id' => 'bar', 'name' => 'bar'],
                (object) ['id' => 'baz', 'name' => 'baz'],
            ]
        );

        $metadata = new MetadataMap([
            'ZFTest\Hal\Plugin\TestAsset\Collection' => [
                'is_collection'       => true,
                'route_name'          => 'hostname/contacts',
                'entity_route_name'   => 'hostname/embedded',
                'links'               => [],
                'force_self_link'     => false,
            ],
        ]);

        $this->plugin->setMetadataMap($metadata);

        $collection = $this->plugin->createCollectionFromMetadata(
            $set,
            $metadata->get('ZFTest\Hal\Plugin\TestAsset\Collection')
        );
        $links = $collection->getLinks();
        $this->assertFalse($links->has('self'));
    }

    public function testCreateCollectionWithoutForcedSelfLinks()
    {
        $collection = ['foo' => 'bar'];
        $metadata = new MetadataMap([
            'ZF\Hal\Collection' => [
                'is_collection'       => true,
                'route_name'          => 'hostname/contacts',
                'entity_route_name'   => 'hostname/embedded',
                'links'               => [],
                'force_self_link'     => false,
            ],
        ]);
        $this->plugin->setMetadataMap($metadata);

        $result = $this->plugin->createCollection($collection);
        $links  = $result->getLinks();
        $this->assertFalse($links->has('self'));
    }

    /**
     * This is a special use-case. See comment in Hal::extractCollection.
     */
    public function testExtractCollectionShouldAddSelfLinkToEntityIfEntityIsArray()
    {
        $object = ['id' => 'Foo'];
        $collection = new Collection([$object]);
        $collection->setEntityRoute('hostname/resource');
        $method = new \ReflectionMethod($this->plugin, 'extractCollection');
        $method->setAccessible(true);
        $result = $method->invoke($this->plugin, $collection);
        $this->assertTrue(isset($result[0]['_links']['self']));
    }

    public function assertIsEntity($entity)
    {
        $this->assertInternalType('array', $entity);
        $this->assertArrayHasKey('_links', $entity, 'Invalid HAL entity; does not contain links');
        $this->assertInternalType('array', $entity['_links']);
    }

    public function assertEntityHasRelationalLink($relation, $entity)
    {
        $this->assertIsEntity($entity);
        $links = $entity['_links'];
        $this->assertArrayHasKey(
            $relation,
            $links,
            sprintf('HAL links do not contain relation "%s"', $relation)
        );
        $link = $links[$relation];
        $this->assertInternalType('array', $link);
    }

    public function assertRelationalLinkEquals($match, $relation, $entity)
    {
        $this->assertEntityHasRelationalLink($relation, $entity);
        $link = $entity['_links'][$relation];
        $this->assertArrayHasKey(
            'href',
            $link,
            sprintf(
                '%s relational link does not have an href; received %s',
                $relation,
                var_export($link, 1)
            )
        );
        $href = $link['href'];
        $this->assertEquals($match, $href);
    }

    public function testRendersEntityWithAssociatedLinks()
    {
        $item = new Entity([
            'foo' => 'bar',
            'id'  => 'identifier',
        ], 'identifier');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(['id' => 'identifier']);
        $links->add($self);

        $result = $this->plugin->renderEntity($item);

        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/identifier', 'self', $result);
        $this->assertArrayHasKey('foo', $result);
        $this->assertEquals('bar', $result['foo']);
    }

    public function testCanRenderStdclassEntity()
    {
        $item = (object) [
            'foo' => 'bar',
            'id'  => 'identifier',
        ];

        $item  = new Entity($item, 'identifier');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(['id' => 'identifier']);
        $links->add($self);

        $result = $this->plugin->renderEntity($item);

        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/identifier', 'self', $result);
        $this->assertArrayHasKey('foo', $result);
        $this->assertEquals('bar', $result['foo']);
    }

    public function testCanSerializeHydratableEntity()
    {
        $this->plugin->addHydrator(
            'ZFTest\Hal\TestAsset\ArraySerializable',
            new Hydrator\ArraySerializable()
        );

        $item  = new HalTestAsset\ArraySerializable();
        $item  = new Entity(new HalTestAsset\ArraySerializable(), 'identifier');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(['id' => 'identifier']);
        $links->add($self);

        $result = $this->plugin->renderEntity($item);

        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/identifier', 'self', $result);
        $this->assertArrayHasKey('foo', $result);
        $this->assertEquals('bar', $result['foo']);
    }

    public function testUsesDefaultHydratorIfAvailable()
    {
        $this->plugin->setDefaultHydrator(
            new Hydrator\ArraySerializable()
        );

        $item  = new HalTestAsset\ArraySerializable();
        $item  = new Entity(new HalTestAsset\ArraySerializable(), 'identifier');
        $links = $item->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource')->setRouteParams(['id' => 'identifier']);
        $links->add($self);

        $result = $this->plugin->renderEntity($item);

        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/identifier', 'self', $result);
        $this->assertArrayHasKey('foo', $result);
        $this->assertEquals('bar', $result['foo']);
    }

    public function testCanRenderNonPaginatedCollection()
    {
        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }

        $collection = new Collection($items);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $result = $this->plugin->renderCollection($collection);

        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource', 'self', $result);

        $this->assertArrayHasKey('_embedded', $result);
        $this->assertInternalType('array', $result['_embedded']);
        $this->assertArrayHasKey('items', $result['_embedded']);
        $this->assertInternalType('array', $result['_embedded']['items']);
        $this->assertEquals(100, count($result['_embedded']['items']));

        foreach ($result['_embedded']['items'] as $key => $item) {
            $id = $key + 1;

            $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/' . $id, 'self', $item);
            $this->assertArrayHasKey('id', $item, var_export($item, 1));
            $this->assertEquals($id, $item['id']);
            $this->assertArrayHasKey('foo', $item);
            $this->assertEquals('bar', $item['foo']);
        }
    }

    public function testCanRenderPaginatedCollection()
    {
        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(3);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource?page=3', 'self', $result);
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource', 'first', $result);
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource?page=20', 'last', $result);
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource?page=2', 'prev', $result);
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource?page=4', 'next', $result);

        $this->assertArrayHasKey('_embedded', $result);
        $this->assertInternalType('array', $result['_embedded']);
        $this->assertArrayHasKey('items', $result['_embedded']);
        $this->assertInternalType('array', $result['_embedded']['items']);
        $this->assertEquals(5, count($result['_embedded']['items']));

        foreach ($result['_embedded']['items'] as $key => $item) {
            $id = $key + 11;

            $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/' . $id, 'self', $item);
            $this->assertArrayHasKey('id', $item, var_export($item, 1));
            $this->assertEquals($id, $item['id']);
            $this->assertArrayHasKey('foo', $item);
            $this->assertEquals('bar', $item['foo']);
        }
    }

    public function invalidPages()
    {
        return [
            '-1'   => [-1],
            '1000' => [1000],
        ];
    }

    /**
     * @dataProvider invalidPages
     */
    public function testRenderingPaginatedCollectionCanReturnApiProblemIfPageIsTooHighOrTooLow($page)
    {
        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator, 'resource');
        $collection->setPageSize(5);

        // Using reflection object so we can force a negative page number if desired
        $r = new ReflectionObject($collection);
        $p = $r->getProperty('page');
        $p->setAccessible(true);
        $p->setValue($collection, $page);

        /* @var \ZF\ApiProblem\ApiProblem*/
        $result = $this->plugin->renderCollection($collection);

        $this->assertInstanceOf('ZF\ApiProblem\ApiProblem', $result, var_export($result, 1));

        $data = $result->toArray();
        $this->assertArrayHasKey('status', $data, var_export($result, 1));
        $this->assertEquals(409, $data['status']);
        $this->assertArrayHasKey('detail', $data);
        $this->assertEquals('Invalid page provided', $data['detail']);
    }

    public function testRendersAttributesAsPartOfNonPaginatedCollection()
    {
        $attributes = [
            'count' => 100,
            'type'  => 'foo',
        ];

        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }

        $collection = new Collection($items, 'resource');
        $collection->setAttributes($attributes);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $this->assertArrayHasKey('count', $result, var_export($result, 1));
        $this->assertEquals(100, $result['count']);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('foo', $result['type']);
    }

    public function testRendersAttributeAsPartOfPaginatedCollection()
    {
        $attributes = [
            'count' => 100,
            'type'  => 'foo',
        ];

        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(3);
        $collection->setAttributes($attributes);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $this->assertArrayHasKey('count', $result, var_export($result, 1));
        $this->assertEquals(100, $result['count']);
        $this->assertArrayHasKey('type', $result);
        $this->assertEquals('foo', $result['type']);
    }

    public function testCanRenderNestedEntitiesAsEmbeddedEntities()
    {
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new Entity([
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ], 'matthew');
        $link = new Link('self');
        $link->setRoute('user')->setRouteParams(['id' => 'matthew']);
        $child->getLinks()->add($link);

        $item = new Entity([
            'foo'  => 'bar',
            'id'   => 'identifier',
            'user' => $child,
        ], 'identifier');
        $link = new Link('self');
        $link->setRoute('resource')->setRouteParams(['id' => 'identifier']);
        $item->getLinks()->add($link);

        $result = $this->plugin->renderEntity($item);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $this->assertArrayHasKey('_embedded', $result);
        $embedded = $result['_embedded'];
        $this->assertArrayHasKey('user', $embedded);
        $user = $embedded['user'];
        $this->assertRelationalLinkContains('/user/matthew', 'self', $user);

        foreach ($child->entity as $key => $value) {
            $this->assertArrayHasKey($key, $user);
            $this->assertEquals($value, $user[$key]);
        }
    }

    public function testRendersEmbeddedEntitiesOfIndividualNonPaginatedCollections()
    {
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new Entity([
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ], 'matthew');
        $link = new Link('self');
        $link->setRoute('user')->setRouteParams(['id' => 'matthew']);
        $child->getLinks()->add($link);

        $prototype = ['foo' => 'bar', 'user' => $child];
        $items = [];
        foreach (range(1, 3) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }

        $collection = new Collection($items);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));

        $collection = $result['_embedded']['items'];
        foreach ($collection as $item) {
            $this->assertArrayHasKey('_embedded', $item);
            $embedded = $item['_embedded'];
            $this->assertArrayHasKey('user', $embedded);

            $user = $embedded['user'];
            $this->assertRelationalLinkContains('/user/matthew', 'self', $user);

            foreach ($child->entity as $key => $value) {
                $this->assertArrayHasKey($key, $user);
                $this->assertEquals($value, $user[$key]);
            }
        }
    }

    public function testRendersEmbeddedEntitiesOfIndividualPaginatedCollections()
    {
        $this->router->addRoute('user', new Segment('/user[/:id]'));

        $child = new Entity([
            'id'     => 'matthew',
            'name'   => 'matthew',
            'github' => 'weierophinney',
        ], 'matthew');
        $link = new Link('self');
        $link->setRoute('user')->setRouteParams(['id' => 'matthew']);
        $child->getLinks()->add($link);

        $prototype = ['foo' => 'bar', 'user' => $child];
        $items = [];
        foreach (range(1, 3) as $id) {
            $item       = $prototype;
            $item['id'] = $id;
            $items[]    = $item;
        }
        $adapter   = new ArrayPaginator($items);
        $paginator = new Paginator($adapter);

        $collection = new Collection($paginator);
        $collection->setPageSize(5);
        $collection->setPage(1);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $collection = $result['_embedded']['items'];
        foreach ($collection as $item) {
            $this->assertArrayHasKey('_embedded', $item, var_export($item, 1));
            $embedded = $item['_embedded'];
            $this->assertArrayHasKey('user', $embedded);

            $user = $embedded['user'];
            $this->assertRelationalLinkContains('/user/matthew', 'self', $user);

            foreach ($child->entity as $key => $value) {
                $this->assertArrayHasKey($key, $user);
                $this->assertEquals($value, $user[$key]);
            }
        }
    }

    public function testAllowsSpecifyingAlternateCallbackForReturningEntityId()
    {
        $this->plugin->getEventManager()->attach('getIdFromEntity', function ($e) {
            $entity = $e->getParam('entity');

            if (!is_array($entity)) {
                return false;
            }

            if (array_key_exists('name', $entity)) {
                return $entity['name'];
            }

            return false;
        }, 10);

        $prototype = ['foo' => 'bar'];
        $items = [];
        foreach (range(1, 100) as $id) {
            $item         = $prototype;
            $item['name'] = $id;
            $items[]      = $item;
        }

        $collection = new Collection($items);
        $collection->setCollectionRoute('resource');
        $collection->setEntityRoute('resource');
        $links = $collection->getLinks();
        $self  = new Link('self');
        $self->setRoute('resource');
        $links->add($self);

        $result = $this->plugin->renderCollection($collection);

        $this->assertInternalType('array', $result, var_export($result, 1));
        $this->assertRelationalLinkEquals('http://localhost.localdomain/resource', 'self', $result);

        $this->assertArrayHasKey('_embedded', $result);
        $this->assertInternalType('array', $result['_embedded']);
        $this->assertArrayHasKey('items', $result['_embedded']);
        $this->assertInternalType('array', $result['_embedded']['items']);
        $this->assertEquals(100, count($result['_embedded']['items']));

        foreach ($result['_embedded']['items'] as $key => $item) {
            $id = $key + 1;

            $this->assertRelationalLinkEquals('http://localhost.localdomain/resource/' . $id, 'self', $item);
            $this->assertArrayHasKey('name', $item, var_export($item, 1));
            $this->assertEquals($id, $item['name']);
            $this->assertArrayHasKey('foo', $item);
            $this->assertEquals('bar', $item['foo']);
        }
    }

    /**
     * @group 100
     */
    public function testRenderEntityPostEventIsTriggered()
    {
        $entity = ['id' => 1, 'foo' => 'bar'];
        $halEntity = new Entity($entity, 1);

        $triggered = false;
        $this->plugin->getEventManager()->attach('renderEntity.post', function ($e) use (&$triggered) {
            $triggered = true;
        });

        $this->plugin->renderEntity($halEntity);
        $this->assertTrue($triggered);
    }
}
