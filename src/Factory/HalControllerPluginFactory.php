<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2016 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\AbstractPluginManager;
use ZF\Hal\Plugin\Hal;

class HalControllerPluginFactory
{
    /**
     * @param ContainerInterface $container
     * @return Hal
     */
    public function __invoke(ContainerInterface $container)
    {
        $container = ($container instanceof AbstractPluginManager)
            ? $container->getServiceLocator()
            : $container;

        $helpers  = $container->get('ViewHelperManager');
        return $helpers->get('Hal');
    }
}
