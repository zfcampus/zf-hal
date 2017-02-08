<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2017 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use Interop\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use Zend\Hydrator\ObjectProperty;
use ZF\Hal\Factory\MetadataMapFactory;
use ZF\Hal\Metadata\Metadata;
use ZF\Hal\Metadata\MetadataMap;
use ZFTest\Hal\Plugin\TestAsset;

class MetadataMapFactoryTest extends TestCase
{
    public function testInstantiatesMetadataMapWithEmptyConfig()
    {
        $services = $this->prophesize(ContainerInterface::class);
        $services->get('ZF\Hal\HalConfig')->willReturn([]);
        $services->has('HydratorManager')->willReturn(false);

        $factory = new MetadataMapFactory();
        $renderer = $factory($services->reveal());

        $this->assertInstanceOf(MetadataMap::class, $renderer);
    }

    public function testInstantiatesMetadataMapWithMetadataMapConfig()
    {
        $config = [
            'metadata_map' => [
                TestAsset\Entity::class => [
                    'hydrator'   => ObjectProperty::class,
                    'route_name' => 'hostname/resource',
                    'route_identifier_name' => 'id',
                    'entity_identifier_name' => 'id',
                ],
                TestAsset\EmbeddedEntity::class => [
                    'hydrator' => ObjectProperty::class,
                    'route'    => 'hostname/embedded',
                    'route_identifier_name' => 'id',
                    'entity_identifier_name' => 'id',
                ],
                TestAsset\EmbeddedEntityWithCustomIdentifier::class => [
                    'hydrator'        => ObjectProperty::class,
                    'route'           => 'hostname/embedded_custom',
                    'route_identifier_name' => 'custom_id',
                    'entity_identifier_name' => 'custom_id',
                ],
            ],
        ];

        $services = $this->prophesize(ContainerInterface::class);
        $services->get('ZF\Hal\HalConfig')->willReturn($config);
        $services->has('HydratorManager')->willReturn(false);

        $factory = new MetadataMapFactory();
        $metadataMap = $factory($services->reveal());

        $this->assertInstanceOf(MetadataMap::class, $metadataMap);

        foreach ($config['metadata_map'] as $key => $value) {
            $this->assertTrue($metadataMap->has($key));
            $this->assertInstanceOf(Metadata::class, $metadataMap->get($key));
        }
    }
}
