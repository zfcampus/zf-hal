<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2016 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Interop\Container\ContainerInterface;
use ZF\Hal\View\HalJsonStrategy;

class HalJsonStrategyFactory
{
    /**
     * @param ContainerInterface $container
     * @return HalJsonStrategy
     */
    public function __invoke(ContainerInterface $container)
    {
        return new HalJsonStrategy($container->get('ZF\Hal\JsonRenderer'));
    }
}
