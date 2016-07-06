<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use ZF\Hal\Extractor\LinkExtractor;
use ZF\Hal\Link\LinkUrlBuilder;

class LinkExtractorFactory
{
    /**
     * @param  \Interop\Container\ContainerInterface|\Zend\ServiceManager\ServiceLocatorInterface $container
     * @return LinkExtractor
     */
    public function __invoke($container)
    {
        return new LinkExtractor($container->get(LinkUrlBuilder::class));
    }
}
