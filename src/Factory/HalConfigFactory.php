<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2016 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Interop\Container\ContainerInterface;

class HalConfigFactory
{
    /**
     * @param ContainerInterface $container
     * @return array|\ArrayAccess
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->has('config')
            ? $container->get('config')
            : [];

        return (isset($config['zf-hal']) && is_array($config['zf-hal']))
            ? $config['zf-hal']
            : [];
    }
}
