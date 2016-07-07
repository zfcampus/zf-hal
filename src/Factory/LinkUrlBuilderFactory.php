<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use ZF\Hal\Link\LinkUrlBuilder;

class LinkUrlBuilderFactory
{
    /**
     * @param  \Interop\Container\ContainerInterface|\Zend\ServiceManager\ServiceLocatorInterface $container
     * @return LinkUrlBuilder
     */
    public function __invoke($container)
    {
        $halConfig = $container->get('ZF\Hal\HalConfig');

        $viewHelperManager = $container->get('ViewHelperManager');

        $serverUrlHelper = $viewHelperManager->get('ServerUrl');
        if (isset($halConfig['options']['use_proxy'])) {
            $serverUrlHelper->setUseProxy($halConfig['options']['use_proxy']);
        }

        $urlHelper = $viewHelperManager->get('Url');

        return new LinkUrlBuilder($serverUrlHelper, $urlHelper);
    }
}
