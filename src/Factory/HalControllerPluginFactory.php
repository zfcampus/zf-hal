<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2016 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\Hal\Plugin\Hal;

class HalControllerPluginFactory implements FactoryInterface
{
    /**
     * @param ContainerInterface $container
     * @param string $requestedName
     * @param null|array $options
     * @return Hal
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $helpers = $container->get('ViewHelperManager');
        return $helpers->get('Hal');
    }

    /**
     * Create service
     *
     * @param ServiceLocatorInterface $container
     * @return Hal
     */
    public function createService(ServiceLocatorInterface $container)
    {
        if ($container instanceof AbstractPluginManager) {
            $container = $container->getServiceLocator() ?: $container;
        }
        return $this($container, Hal::class);
    }
}
