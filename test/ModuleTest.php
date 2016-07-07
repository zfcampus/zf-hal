<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal;

use Zend\ServiceManager\ServiceManager;
use Zend\View\View;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\Hal\Module;
use ZF\Hal\View\HalJsonModel;
use PHPUnit_Framework_TestCase as TestCase;
use stdClass;
use ZF\Hal\View\HalJsonRenderer;
use ZF\Hal\View\HalJsonStrategy;

class ModuleTest extends TestCase
{
    private $module;

    public function setUp()
    {
        $this->module = new Module;
    }

    public function testOnRenderWhenMvcEventResultIsNotHalJsonModel()
    {
        $mvcEvent = $this->getMockBuilder('Zend\Mvc\MvcEvent')->getMock();
        $mvcEvent
            ->expects($this->once())
            ->method('getResult')
            ->will($this->returnValue(new stdClass()));
        $mvcEvent
            ->expects($this->never())
            ->method('getTarget');

        $this->module->onRender($mvcEvent);
    }

    public function testOnRenderAttachesJsonStrategy()
    {
        $strategy = new HalJsonStrategy(new HalJsonRenderer(new ApiProblemRenderer()));

        $view = new View();

        $eventManager = $this->getMockBuilder('Zend\EventManager\EventManager')->getMock();
        $eventManager
            ->expects($this->exactly(2))
            ->method('attach');

        $view->setEventManager($eventManager);

        $serviceManager = new ServiceManager();
        $serviceManager->setService('ZF\Hal\JsonStrategy', $strategy);
        $serviceManager->setService('View', $view);

        $application = $this->getMockBuilder('Zend\Mvc\ApplicationInterface')->getMock();
        $application
            ->expects($this->once())
            ->method('getServiceManager')
            ->will($this->returnValue($serviceManager));

        $mvcEvent = $this->getMockBuilder('Zend\Mvc\MvcEvent')->getMock();
        $mvcEvent
            ->expects($this->at(0))
            ->method('getResult')
            ->will($this->returnValue(new HalJsonModel()));
        $mvcEvent
            ->expects($this->at(1))
            ->method('getTarget')
            ->will($this->returnValue($application));

        $this->module->onRender($mvcEvent);
    }
}
