<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\Hal\Extractor\LinkCollectionExtractor;

class LinkCollectionExtractorFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return LinkCollectionExtractor
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $linkExtractor = $serviceLocator->get('ZF\Hal\Extractor\LinkExtractor');

        return new LinkCollectionExtractor($linkExtractor);
    }
}
