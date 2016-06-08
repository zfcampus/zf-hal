<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\ServiceManager\ServiceManager;
use ZF\ApiProblem\Factory\ApiProblemRendererFactory;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\Hal\Factory\HalJsonRendererFactory;
use ZF\Hal\View\HalJsonRenderer;

class HalJsonRendererFactoryTest extends TestCase
{
    public function testInstantiatesHalJsonRenderer()
    {
        $services = new ServiceManager();

        $viewHelperManager = $this->getMockBuilder('Zend\View\HelperPluginManager')
            ->disableOriginalConstructor()
            ->getMock();

        $services->setService('ViewHelperManager', $viewHelperManager);

        $services->setInvokableClass(ApiProblemRenderer::class, ApiProblemRenderer::class);

        $factory = new HalJsonRendererFactory();
        $renderer = $factory($services,'ZF\Hal\JsonRenderer');

        $this->assertInstanceOf('ZF\Hal\View\HalJsonRenderer', $renderer);
    }
}
