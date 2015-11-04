<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\Hal\Exception;
use ZF\Hal\Extractor\LinkCollectionExtractor;
use ZF\Hal\Extractor\LinkExtractor;
use ZF\Hal\Plugin;

class HalViewHelperFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @throws Exception\DomainException
     * @return Plugin\Hal
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $services        = $serviceLocator->getServiceLocator();
        $halConfig       = $services->get('ZF\Hal\HalConfig');
        /* @var $rendererOptions \ZF\Hal\RendererOptions */
        $rendererOptions = $services->get('ZF\Hal\RendererOptions');
        $metadataMap     = $services->get('ZF\Hal\MetadataMap');
        $hydrators       = $metadataMap->getHydratorManager();

        $serverUrlHelper = $serviceLocator->get('ServerUrl');
        if (isset($halConfig['options']['use_proxy'])) {
            $serverUrlHelper->setUseProxy($halConfig['options']['use_proxy']);
        }

        $urlHelper = $serviceLocator->get('Url');

        $helper = new Plugin\Hal($hydrators);
        $helper
            ->setMetadataMap($metadataMap)
            ->setServerUrlHelper($serverUrlHelper)
            ->setUrlHelper($urlHelper);

        $linkExtractor = new LinkExtractor($serverUrlHelper, $urlHelper);
        $linkCollectionExtractor = new LinkCollectionExtractor($linkExtractor);
        $helper->setLinkCollectionExtractor($linkCollectionExtractor);

        $defaultHydrator = $rendererOptions->getDefaultHydrator();
        if ($defaultHydrator) {
            if (! $hydrators->has($defaultHydrator)) {
                throw new Exception\DomainException(sprintf(
                    'Cannot locate default hydrator by name "%s" via the HydratorManager',
                    $defaultHydrator
                ));
            }

            $hydrator = $hydrators->get($defaultHydrator);
            $helper->setDefaultHydrator($hydrator);
        }

        $helper->setRenderEmbeddedEntities($rendererOptions->getRenderEmbeddedEntities());
        $helper->setRenderCollections($rendererOptions->getRenderEmbeddedCollections());

        $hydratorMap = $rendererOptions->getHydrators();
        foreach ($hydratorMap as $class => $hydratorServiceName) {
            $helper->addHydrator($class, $hydratorServiceName);
        }

        return $helper;
    }
}
