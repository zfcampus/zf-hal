<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2017 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit\Framework\TestCase;
use Zend\ServiceManager\ServiceManager;
use Zend\View\HelperPluginManager;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\Hal\Factory\HalJsonRendererFactory;
use ZF\Hal\View\HalJsonRenderer;

class HalJsonRendererFactoryTest extends TestCase
{
    public function testInstantiatesHalJsonRenderer()
    {
        $viewHelperManager = $this->createMock(HelperPluginManager::class);

        $services = new ServiceManager();
        $services->setService('ViewHelperManager', $viewHelperManager);
        $services->setInvokableClass(ApiProblemRenderer::class, ApiProblemRenderer::class);

        $factory = new HalJsonRendererFactory();
        $renderer = $factory($services, 'ZF\Hal\JsonRenderer');

        $this->assertInstanceOf(HalJsonRenderer::class, $renderer);
    }
}
