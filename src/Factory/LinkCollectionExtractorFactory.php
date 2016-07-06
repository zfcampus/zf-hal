<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use ZF\Hal\Extractor\LinkCollectionExtractor;
use ZF\Hal\Extractor\LinkExtractor;

class LinkCollectionExtractorFactory
{
    /**
     * @param  \Interop\Container\ContainerInterface|\Zend\ServiceManager\ServiceLocatorInterface $container
     * @return LinkCollectionExtractor
     */
    public function __invoke($container)
    {
        return new LinkCollectionExtractor($container->get(LinkExtractor::class));
    }
}
