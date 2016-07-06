<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\Hal\Extractor\LinkExtractor;

class LinkExtractorFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return LinkExtractor
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $linkUrlBuilder = $serviceLocator->get('ZF\Hal\Link\LinkUrlBuilder');

        return new LinkExtractor($linkUrlBuilder);
    }
}
