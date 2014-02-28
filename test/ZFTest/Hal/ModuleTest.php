<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal;

use ZF\Hal\Module;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\Router\RouteMatch;
use Zend\ServiceManager\Config;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\ArrayUtils;

class ModuleTest extends TestCase
{
    public function setUp()
    {
        $this->module = new Module;
    }

    public function setupServiceManager()
    {
        $options = array('service_manager' => array(
            'factories' => array(
                // Consumed by ZF\Hal\JsonRenderer service
                'ViewHelperManager'       => 'Zend\Mvc\Service\ViewHelperManagerFactory',
                'ControllerPluginManager' => 'Zend\Mvc\Service\ControllerPluginManagerFactory',
            ),
        ));
        $config = ArrayUtils::merge($options['service_manager'], $this->module->getServiceConfig());
        $config['view_helpers']       = $this->module->getViewHelperConfig();
        $config['controller_plugins'] = $this->module->getControllerPluginConfig();

        $services       = new ServiceManager();
        $servicesConfig = new Config($config);
        $servicesConfig->configureServiceManager($services);
        $services->setService('Config', $config);

        $event = new MvcEvent();
        $event->setRouteMatch(new RouteMatch(array()));

        $router = $this->getMock('Zend\Mvc\Router\RouteStackInterface');
        $services->setService('HttpRouter', $router);

        $app = $this->getMockBuilder('Zend\Mvc\Application')
                    ->disableOriginalConstructor()
                    ->getMock();
        $app->expects($this->once())
            ->method('getMvcEvent')
            ->will($this->returnValue($event));
        $services->setService('application', $app);

        $helpers = $services->get('ViewHelperManager');
        $helpersConfig = new Config($config['view_helpers']);
        $helpersConfig->configureServiceManager($helpers);

        $plugins = $services->get('ControllerPluginManager');
        $pluginsConfig = new Config($config['controller_plugins']);
        $pluginsConfig->configureServiceManager($plugins);

        return $services;
    }

    public function testJsonRendererFactoryInjectsDefaultHydratorIfPresentInConfig()
    {
        $options = array(
            'zf-hal' => array(
                'renderer' => array(
                    'default_hydrator' => 'ObjectProperty',
                ),
            ),
        );

        $services = $this->setupServiceManager();
        $config   = $services->get('Config');
        $services->setAllowOverride(true);
        $services->setService('Config', ArrayUtils::merge($config, $options));

        $helpers = $services->get('ViewHelperManager');
        $plugin  = $helpers->get('Hal');
        $this->assertAttributeInstanceOf('Zend\Stdlib\Hydrator\ObjectProperty', 'defaultHydrator', $plugin);
    }

    public function testJsonRendererFactoryInjectsHydratorMappingsIfPresentInConfig()
    {
        $options = array(
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

        $services = $this->setupServiceManager();
        $config   = $services->get('Config');
        $services->setAllowOverride(true);
        $services->setService('Config', ArrayUtils::merge($config, $options));

        $helpers = $services->get('ViewHelperManager');
        $plugin  = $helpers->get('Hal');

        $r             = new ReflectionObject($plugin);
        $hydratorsProp = $r->getProperty('hydratorMap');
        $hydratorsProp->setAccessible(true);
        $hydratorMap = $hydratorsProp->getValue($plugin);

        $hydrators   = $plugin->getHydratorManager();

        $this->assertInternalType('array', $hydratorMap);

        foreach ($options['zf-hal']['renderer']['hydrators'] as $class => $serviceName) {
            $key = strtolower($class);
            $this->assertArrayHasKey($key, $hydratorMap);
            $hydrator = $hydratorMap[$key];
            $this->assertSame(get_class($hydrators->get($serviceName)), get_class($hydrator));
        }
    }
}
