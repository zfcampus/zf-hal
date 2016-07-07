<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\AbstractPluginManager;
use ZF\Hal\Exception;
use ZF\Hal\Extractor\LinkCollectionExtractor;
use ZF\Hal\Link;
use ZF\Hal\Plugin;

class HalViewHelperFactory
{
    /**
     * @param  ContainerInterface|\Zend\ServiceManager\ServiceLocatorInterface $container
     * @return Plugin\Hal
     */
    public function __invoke(ContainerInterface $container)
    {
        $container = ($container instanceof AbstractPluginManager)
            ? $container->getServiceLocator()
            : $container;

        /* @var $rendererOptions \ZF\Hal\RendererOptions */
        $rendererOptions = $container->get('ZF\Hal\RendererOptions');
        $metadataMap     = $container->get('ZF\Hal\MetadataMap');
        $hydrators       = $metadataMap->getHydratorManager();

        $helper = new Plugin\Hal($hydrators);
        $helper->setMetadataMap($metadataMap);

        $linkUrlBuilder = $container->get(Link\LinkUrlBuilder::class);
        $helper->setLinkUrlBuilder($linkUrlBuilder);

        $linkCollectionExtractor = $container->get(LinkCollectionExtractor::class);
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

    /**
     * Proxies to __invoke() to provide backwards compatibility.
     *
     * @deprecated since 1.4.0; use __invoke instead.
     * @param  \Zend\ServiceManager\ServiceLocatorInterface $container
     * @return Plugin\Hal
     */
    public function createService($container)
    {
        return $this($container);
    }
}
