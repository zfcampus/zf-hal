<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Hydrator\HydratorPluginManager;
use Zend\ServiceManager\ServiceManager;
use ZF\Hal\EntityHydratorManager;
use ZF\Hal\Extractor\EntityExtractor;
use ZF\Hal\Metadata\MetadataMap;
use ZF\Hal\ResourceFactory;
use ZFTest\Hal\Plugin\TestAsset as HalPluginTestAsset;

/**
 * @subpackage UnitTest
 */
class ResourceFactoryTest extends TestCase
{
    /**
     * @group 79
     */
    public function testInjectsLinksFromMetadataWhenCreatingEntity()
    {
        $object = new HalPluginTestAsset\Entity('foo', 'Foo');

        $metadata = new MetadataMap([
            HalPluginTestAsset\Entity::class => [
                'hydrator'   => 'Zend\Hydrator\ObjectProperty',
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
        $metadata->setHydratorManager(new HydratorPluginManager(new ServiceManager()));

        $resourceFactory = $this->getResourceFactory($metadata);

        $entity = $resourceFactory->createEntityFromMetadata(
            $object,
            $metadata->get(HalPluginTestAsset\Entity::class)
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
        $object = new HalPluginTestAsset\Entity('foo', 'Foo');

        $entityDefiningCallback = new TestAsset\EntityDefiningCallback($this, $object);

        $test = $this;

        $metadata = new MetadataMap([
            HalPluginTestAsset\Entity::class => [
                'hydrator'     => 'Zend\Hydrator\ObjectProperty',
                'route_name'   => 'hostname/resource',
                'route_params' => [
                    'test-1' => [$entityDefiningCallback, 'callback'],
                    'test-2' => function ($expected) use ($object, $test) {
                        $test->assertSame($expected, $object);
                        $test->assertSame($object, $this);

                        return 'closure-param';
                    },
                ],
            ],
        ]);

        $resourceFactory = $this->getResourceFactory($metadata);

        $entity = $resourceFactory->createEntityFromMetadata(
            $object,
            $metadata->get(HalPluginTestAsset\Entity::class)
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
    public function testInjectsLinksFromMetadataWhenCreatingCollection()
    {
        $set = new HalPluginTestAsset\Collection([
            (object) ['id' => 'foo', 'name' => 'foo'],
            (object) ['id' => 'bar', 'name' => 'bar'],
            (object) ['id' => 'baz', 'name' => 'baz'],
        ]);

        $metadata = new MetadataMap([
            HalPluginTestAsset\Collection::class => [
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
        $metadata->setHydratorManager(new HydratorPluginManager(new ServiceManager()));

        $resourceFactory = $this->getResourceFactory($metadata);

        $collection = $resourceFactory->createCollectionFromMetadata(
            $set,
            $metadata->get(HalPluginTestAsset\Collection::class)
        );

        $this->assertInstanceof('ZF\Hal\Collection', $collection);
        $links = $collection->getLinks();
        $this->assertTrue($links->has('describedby'));
        $link = $links->get('describedby');
        $this->assertTrue($link->hasUrl());
        $this->assertEquals('http://example.com/api/help/collection', $link->getUrl());
    }

    private function getResourceFactory(MetadataMap $metadata)
    {
        $hydratorPluginManager = new HydratorPluginManager(new ServiceManager());
        $entityHydratorManager = new EntityHydratorManager($hydratorPluginManager, $metadata);
        $entityExtractor       = new EntityExtractor($entityHydratorManager);

        return new ResourceFactory($entityHydratorManager, $entityExtractor);
    }
}
