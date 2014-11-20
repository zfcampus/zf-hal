<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
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
            ->will($this->returnValue(new HalPlugin()));

        $services = new ServiceManager();
        $services->setService('ViewHelperManager', $viewHelperManager);

        $pluginManager = $this->getMock('Zend\ServiceManager\AbstractPluginManager');
        $pluginManager
            ->expects($this->once())
            ->method('getServiceLocator')
            ->will($this->returnValue($services));

        $factory = new HalControllerPluginFactory();
        $plugin = $factory->createService($pluginManager);

        $this->assertInstanceOf('ZF\Hal\Plugin\Hal', $plugin);
    }
}
