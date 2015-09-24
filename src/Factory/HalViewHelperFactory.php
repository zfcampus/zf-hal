<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\Hal\Exception;
use ZF\Hal\Plugin;

class HalViewHelperFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return Plugin\Hal
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $services        = $serviceLocator->getServiceLocator();

        /* @var $rendererOptions \ZF\Hal\RendererOptions */
        $rendererOptions = $services->get('ZF\Hal\RendererOptions');
        $metadataMap     = $services->get('ZF\Hal\MetadataMap');
        $hydrators       = $metadataMap->getHydratorManager();

        $helper = new Plugin\Hal($hydrators);
        $helper->setMetadataMap($metadataMap);

        $linkUrlBuilder = $services->get('ZF\Hal\Link\LinkUrlBuilder');
        $helper->setLinkUrlBuilder($linkUrlBuilder);

        $linkCollectionExtractor = $services->get('ZF\Hal\Extractor\LinkCollectionExtractor');
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
