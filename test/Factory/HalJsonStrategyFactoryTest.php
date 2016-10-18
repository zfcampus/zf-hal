<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\ServiceManager\ServiceManager;
use ZF\Hal\Factory\HalJsonStrategyFactory;
use ZF\Hal\View\HalJsonRenderer;
use ZF\Hal\View\HalJsonStrategy;

class HalJsonStrategyFactoryTest extends TestCase
{
    public function testInstantiatesHalJsonStrategy()
    {
        $halJsonRenderer = $this->createMock(HalJsonRenderer::class);

        $services = new ServiceManager();
        $services->setService('ZF\Hal\JsonRenderer', $halJsonRenderer);

        $factory = new HalJsonStrategyFactory();
        $strategy = $factory($services);

        $this->assertInstanceOf(HalJsonStrategy::class, $strategy);
    }
}
