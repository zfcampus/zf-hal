<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\Hal\Link\LinkUrlBuilder;

class LinkUrlBuilderFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return LinkUrlBuilder
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $halConfig = $serviceLocator->get('ZF\Hal\HalConfig');

        $viewHelperManager = $serviceLocator->get('ViewHelperManager');

        $serverUrlHelper = $viewHelperManager->get('ServerUrl');
        if (isset($halConfig['options']['use_proxy'])) {
            $serverUrlHelper->setUseProxy($halConfig['options']['use_proxy']);
        }

        $urlHelper = $viewHelperManager->get('Url');

        return new LinkUrlBuilder($serverUrlHelper, $urlHelper);
    }
}
