<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Interop\Container\ContainerInterface;
use Zend\Hydrator\HydratorInterface;
use Zend\Hydrator\HydratorPluginManager;
use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\Hal\Exception;
use ZF\Hal\Extractor\LinkCollectionExtractor;
use ZF\Hal\Link;
use ZF\Hal\Plugin;

class HalViewHelperFactory
{
    /**
     * @param  ContainerInterface|ServiceLocatorInterface $container
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

        /** @var HydratorPluginManager $hydrators */
        $hydrators       = $metadataMap->getHydratorManager();

        $helper = new Plugin\Hal($hydrators);

        if ($container->has('EventManager')) {
            $helper->setEventManager($container->get('EventManager'));
        }

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

            /** @var HydratorInterface $hydrator */
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
     * @param  ServiceLocatorInterface $container
     * @return Plugin\Hal
     */
    public function createService($container)
    {
        return $this($container);
    }
}
