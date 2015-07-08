<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class HalConfigFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = array();
        if ($serviceLocator->has('config')) {
            $config = $serviceLocator->get('config');
        }

        $halConfig = array();
        if (isset($config['zf-hal']) && is_array($config['zf-hal'])) {
            $halConfig = $config['zf-hal'];
        }

        return $halConfig;
    }
}
