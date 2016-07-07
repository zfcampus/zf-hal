<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Hydrator\HydratorPluginManager;
use ZF\Hal\Metadata;

class MetadataMapFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string             $requestedName
     * @param  null|array         $options
     *
     * @return object
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = NULL)
    {
        $config = $container->get('ZF\Hal\HalConfig');

        if ($container->has('HydratorManager')) {
            $hydrators = $container->get('HydratorManager');
        } else {
            $hydrators = new HydratorPluginManager($container);
        }

        $map = [];
        if (isset($config['metadata_map']) && is_array($config['metadata_map'])) {
            $map = $config['metadata_map'];
        }

        return new Metadata\MetadataMap($map, $hydrators);
    }

}
