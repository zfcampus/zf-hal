<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2016 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Interop\Container\ContainerInterface;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\Hal\View\HalJsonRenderer;

class HalJsonRendererFactory
{
    /**
     * @param ContainerInterface $container
     * @return HalJsonRenderer
     */
    public function __invoke(ContainerInterface $container)
    {
        $helpers            = $container->get('ViewHelperManager');
        $apiProblemRenderer = $container->get(ApiProblemRenderer::class);

        $renderer = new HalJsonRenderer($apiProblemRenderer);
        $renderer->setHelperPluginManager($helpers);

        return $renderer;
    }
}
