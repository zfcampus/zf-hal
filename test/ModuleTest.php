<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal;

use PHPUnit_Framework_TestCase as TestCase;
use Prophecy\Argument;
use stdClass;
use Zend\EventManager\EventManager;
use Zend\Mvc\ApplicationInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceManager;
use Zend\View\View;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\Hal\Module;
use ZF\Hal\View\HalJsonModel;
use ZF\Hal\View\HalJsonRenderer;
use ZF\Hal\View\HalJsonStrategy;

class ModuleTest extends TestCase
{
    /**
     * @var Module
     */
    private $module;

    public function setUp()
    {
        $this->module = new Module();
    }

    public function testOnRenderWhenMvcEventResultIsNotHalJsonModel()
    {
        $mvcEvent = $this->prophesize(MvcEvent::class);
        $mvcEvent->getResult()->willReturn(new stdClass())->shouldBeCalledTimes(1);
        $mvcEvent->getTarget()->shouldNotBeCalled();

        $this->module->onRender($mvcEvent->reveal());
    }

    public function testOnRenderAttachesJsonStrategy()
    {
        $strategy = new HalJsonStrategy(new HalJsonRenderer(new ApiProblemRenderer()));

        $view = new View();

        $eventManager = $this->createMock(EventManager::class);
        $eventManager
            ->expects($this->exactly(2))
            ->method('attach');

        $view->setEventManager($eventManager);

        $serviceManager = new ServiceManager();
        $serviceManager->setService('ZF\Hal\JsonStrategy', $strategy);
        $serviceManager->setService('View', $view);

        $application = $this->prophesize(ApplicationInterface::class);
        $application->getServiceManager()->willReturn($serviceManager);

        $mvcEvent = $this->prophesize(MvcEvent::class);
        $mvcEvent->getResult()->willReturn(new HalJsonModel());
        $mvcEvent->getTarget()->willReturn($application->reveal());

        $this->module->onRender($mvcEvent->reveal());
    }
}
