<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Hydrator\HydratorPluginManager;
use Zend\ServiceManager\ServiceManager;
use ZF\Hal\Factory\HalControllerPluginFactory;
use ZF\Hal\Plugin\Hal as HalPlugin;

class HalControllerPluginFactoryTest extends TestCase
{
    public function testInstantiatesHalJsonRenderer()
    {
        $viewHelperManager = $this->getMockBuilder('Zend\View\HelperPluginManager')
            ->disableOriginalConstructor()
            ->getMock();
        $viewHelperManager
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue(new HalPlugin(new HydratorPluginManager(new ServiceManager()))));

        $services = new ServiceManager();
        $services->setService('ViewHelperManager', $viewHelperManager);

        $factory = new HalControllerPluginFactory();
        $plugin = $factory($services, 'Hal');

        $this->assertInstanceOf('ZF\Hal\Plugin\Hal', $plugin);
    }

    public function testInstantiatesHalJsonRendererWithV2()
    {
        $viewHelperManager = $this->getMockBuilder('Zend\View\HelperPluginManager')
            ->disableOriginalConstructor()
            ->getMock();
        $viewHelperManager
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue(new HalPlugin(new HydratorPluginManager(new ServiceManager()))));

        $services = new ServiceManager();
        $services->setService('ViewHelperManager', $viewHelperManager);

        $pluginManager = $this->getMockBuilder('Zend\ServiceManager\AbstractPluginManager')
            ->disableOriginalConstructor()
            ->getMock();
        $pluginManager->expects($this->once())
            ->method('getServiceLocator')
            ->willReturn($services);

        $factory = new HalControllerPluginFactory();
        $plugin = $factory->createService($pluginManager);

        $this->assertInstanceOf('ZF\Hal\Plugin\Hal', $plugin);
    }
}
