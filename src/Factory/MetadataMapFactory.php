<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stdlib\Hydrator\HydratorPluginManager;
use ZF\Hal\Metadata;

class MetadataMapFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return Metadata\MetadataMap
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = array();
        if ($serviceLocator->has('config')) {
            $config = $serviceLocator->get('config');
        }

        if ($serviceLocator->has('HydratorManager')) {
            $hydrators = $serviceLocator->get('HydratorManager');
        } else {
            $hydrators = new HydratorPluginManager();
        }

        $map = array();
        if (isset($config['zf-hal'])
            && isset($config['zf-hal']['metadata_map'])
            && is_array($config['zf-hal']['metadata_map'])
        ) {
            $map = $config['zf-hal']['metadata_map'];
        }

        return new Metadata\MetadataMap($map, $hydrators);
    }
}
