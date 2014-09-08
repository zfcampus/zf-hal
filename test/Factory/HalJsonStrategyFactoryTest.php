<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\ServiceManager\ServiceManager;
use ZF\Hal\Factory\HalJsonStrategyFactory;

class HalJsonStrategyFactoryTest extends TestCase
{
    public function testInstantiatesHalJsonStrategy()
    {
        $services = new ServiceManager();

        $halJsonRenderer = $this->getMockBuilder('ZF\Hal\View\HalJsonRenderer')
            ->disableOriginalConstructor()
            ->getMock();

        $services->setService('ZF\Hal\JsonRenderer', $halJsonRenderer);

        $factory = new HalJsonStrategyFactory();
        $strategy = $factory->createService($services);

        $this->assertInstanceOf('ZF\Hal\View\HalJsonStrategy', $strategy);
    }
}
