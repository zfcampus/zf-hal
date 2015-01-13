<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\Hydrator\HydratorPluginManager;
use Zend\View\Helper\ServerUrl;
use Zend\View\Helper\Url;
use ZF\Hal\Factory\HalViewHelperFactory;
use ZF\Hal\Plugin;

class HalViewHelperFactoryTest extends TestCase
{
    public function setupPluginManager($config = array())
    {
        $services = new ServiceManager();

        $services->setService('Config', $config);

        $metadataMap = $this->getMock('ZF\Hal\Metadata\MetadataMap');
        $metadataMap
            ->expects($this->once())
            ->method('getHydratorManager')
            ->will($this->returnValue(new HydratorPluginManager()));

        $services->setService('ZF\Hal\MetadataMap', $metadataMap);

        $this->pluginManager = $this->getMock('Zend\ServiceManager\AbstractPluginManager');

        $this->pluginManager
            ->expects($this->at(1))
            ->method('get')
            ->with('ServerUrl')
            ->will($this->returnValue(new ServerUrl()));

        $this->pluginManager
            ->expects($this->at(2))
            ->method('get')
            ->with('Url')
            ->will($this->returnValue(new Url()));

        $this->pluginManager
            ->expects($this->any())
            ->method('getServiceLocator')
            ->will($this->returnValue($services));
    }

    public function testInstantiatesHalViewHelper()
    {
        $this->setupPluginManager();

        $factory = new HalViewHelperFactory();
        $plugin = $factory->createService($this->pluginManager);

        $this->assertInstanceOf('ZF\Hal\Plugin\Hal', $plugin);
    }

    public function testHalViewHelperFactoryInjectsDefaultHydratorIfPresentInConfig()
    {
        $config = array(
            'zf-hal' => array(
                'renderer' => array(
                    'default_hydrator' => 'ObjectProperty',
                ),
            ),
        );

        $this->setupPluginManager($config);

        $factory = new HalViewHelperFactory();
        $plugin = $factory->createService($this->pluginManager);

        $this->assertInstanceOf('ZF\Hal\Plugin\Hal', $plugin);
        $this->assertAttributeInstanceOf('Zend\Stdlib\Hydrator\ObjectProperty', 'defaultHydrator', $plugin);
    }

    public function testHalViewHelperFactoryInjectsHydratorMappingsIfPresentInConfig()
    {
        $config = array(
            'zf-hal' => array(
                'renderer' => array(
                    'hydrators' => array(
                        'Some\MadeUp\Component'            => 'ClassMethods',
                        'Another\MadeUp\Component'         => 'Reflection',
                        'StillAnother\MadeUp\Component'    => 'ArraySerializable',
                        'A\Component\With\SharedHydrators' => 'Reflection',
                    ),
                ),
            ),
        );

        $this->setupPluginManager($config);

        $factory = new HalViewHelperFactory();
        $plugin = $factory->createService($this->pluginManager);

        $r             = new ReflectionObject($plugin);
        $hydratorsProp = $r->getProperty('hydratorMap');
        $hydratorsProp->setAccessible(true);
        $hydratorMap = $hydratorsProp->getValue($plugin);

        $hydrators = $plugin->getHydratorManager();

        $this->assertInternalType('array', $hydratorMap);

        foreach ($config['zf-hal']['renderer']['hydrators'] as $class => $serviceName) {
            $key = strtolower($class);
            $this->assertArrayHasKey($key, $hydratorMap);

            $hydrator = $hydratorMap[$key];
            $this->assertSame(get_class($hydrators->get($serviceName)), get_class($hydrator));
        }
    }

    /**
     * @group fail
     */
    public function testOptionUseProxyIfPresentInConfig()
    {
        $options = array(
            'zf-hal' => array(
                'options' => array(
                    'use_proxy' => true,
                ),
            ),
        );

        $this->setupPluginManager($options);

        $factory = new HalViewHelperFactory();
        $halPlugin = $factory->createService($this->pluginManager);

        $r = new ReflectionObject($halPlugin);
        $p = $r->getProperty('serverUrlHelper');
        $p->setAccessible(true);
        $serverUrlPlugin = $p->getValue($halPlugin);
        $this->assertInstanceOf('Zend\View\Helper\ServerUrl', $serverUrlPlugin);

        $r = new ReflectionObject($serverUrlPlugin);
        $p = $r->getProperty('useProxy');
        $p->setAccessible(true);
        $useProxy = $p->getValue($serverUrlPlugin);
        $this->assertInternalType('boolean', $useProxy);
        $this->assertTrue($useProxy);
    }
}
