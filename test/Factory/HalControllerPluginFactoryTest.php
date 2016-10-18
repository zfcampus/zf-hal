<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Hydrator\HydratorPluginManager;
use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\ServiceManager;
use Zend\View\HelperPluginManager;
use ZF\Hal\Factory\HalControllerPluginFactory;
use ZF\Hal\Plugin\Hal as HalPlugin;

class HalControllerPluginFactoryTest extends TestCase
{
    public function testInstantiatesHalJsonRenderer()
    {
        $viewHelperManager = $this->prophesize(HelperPluginManager::class);
        $viewHelperManager->get('Hal')
            ->willReturn(new HalPlugin(new HydratorPluginManager(new ServiceManager())))
            ->shouldBeCalledTimes(1);

        $services = new ServiceManager();
        $services->setService('ViewHelperManager', $viewHelperManager->reveal());

        $factory = new HalControllerPluginFactory();
        $plugin = $factory($services, 'Hal');

        $this->assertInstanceOf(HalPlugin::class, $plugin);
    }

    public function testInstantiatesHalJsonRendererWithV2()
    {
        $viewHelperManager = $this->prophesize(HelperPluginManager::class);
        $viewHelperManager->get('Hal')
            ->willReturn(new HalPlugin(new HydratorPluginManager(new ServiceManager())))
            ->shouldBeCalledTimes(1);

        $services = new ServiceManager();
        $services->setService('ViewHelperManager', $viewHelperManager->reveal());

        $pluginManager = $this->prophesize(AbstractPluginManager::class);
        $pluginManager->getServiceLocator()
            ->willReturn($services)
            ->shouldBeCalledTimes(1);

        $factory = new HalControllerPluginFactory();
        $plugin = $factory->createService($pluginManager->reveal());

        $this->assertInstanceOf(HalPlugin::class, $plugin);
    }
}
