<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\Hal\View\HalJsonRenderer;

class HalJsonRendererFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return HalJsonRenderer
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $helpers            = $serviceLocator->get('ViewHelperManager');
        $apiProblemRenderer = $serviceLocator->get('ZF\ApiProblem\ApiProblemRenderer');

        $renderer = new HalJsonRenderer($apiProblemRenderer);
        $renderer->setHelperPluginManager($helpers);

        return $renderer;
    }
}
