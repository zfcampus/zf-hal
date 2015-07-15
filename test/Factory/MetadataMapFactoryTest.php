<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use ZF\Hal\Factory\MetadataMapFactory;

class MetadataMapFactoryTest extends TestCase
{
    public function testInstantiatesMetadataMapWithEmptyConfig()
    {
        $services = $this->getMock('Zend\ServiceManager\ServiceLocatorInterface');

        $services
            ->expects($this->at(0))
            ->method('has')
            ->with('config')
            ->will($this->returnValue(false));

        $services
            ->expects($this->at(1))
            ->method('has')
            ->with('HydratorManager')
            ->will($this->returnValue(false));

        $factory = new MetadataMapFactory();
        $renderer = $factory->createService($services);

        $this->assertInstanceOf('ZF\Hal\Metadata\MetadataMap', $renderer);
    }

    public function testInstantiatesMetadataMapWithMetadataMapConfig()
    {
        $services = $this->getMock('Zend\ServiceManager\ServiceLocatorInterface');

        $services
            ->expects($this->at(0))
            ->method('has')
            ->with('config')
            ->will($this->returnValue(true));

        $config = [
            'zf-hal' => [
                'metadata_map' => [
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
                        'force_self_link' => true, // same as previous
                    ],
                    'ZFTest\Hal\Plugin\TestAsset\EmbeddedEntityWithCustomIdentifier' => [
                        'hydrator'        => 'Zend\Stdlib\Hydrator\ObjectProperty',
                        'route'           => 'hostname/embedded_custom',
                        'route_identifier_name' => 'custom_id',
                        'entity_identifier_name' => 'custom_id',
                        'force_self_link' => false,
                    ],
                ],
            ],
        ];

        $services
            ->expects($this->at(1))
            ->method('get')
            ->with('config')
            ->will($this->returnValue($config));

        $services
            ->expects($this->at(2))
            ->method('has')
            ->with('HydratorManager')
            ->will($this->returnValue(false));

        $factory = new MetadataMapFactory();
        $metadataMap = $factory->createService($services);

        $this->assertInstanceOf('ZF\Hal\Metadata\MetadataMap', $metadataMap);

        foreach ($config['zf-hal']['metadata_map'] as $key => $value) {
            $this->assertTrue($metadataMap->has($key));
            $this->assertInstanceOf('ZF\Hal\Metadata\Metadata', $metadataMap->get($key));
        }
    }
}
