<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

/**
 * Class HalConfigFactory
 *
 * @package ZF\Hal\Factory
 */
class HalConfigFactory implements FactoryInterface
{
    /**
     * @param \Interop\Container\ContainerInterface $container
     * @param string                                $requestedName
     * @param array|NULL                            $options
     *
     * @return array|mixed
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = NULL)
    {
        $config = [];
        if ($container->has('config')) {
            $config = $container->get('config');
        }

        $halConfig = [];
        if (isset($config['zf-hal']) && is_array($config['zf-hal'])) {
            $halConfig = $config['zf-hal'];
        }

        return $halConfig;
    }


}
