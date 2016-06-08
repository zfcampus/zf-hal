<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Factory;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\Hal\Exception;
use ZF\Hal\Extractor\LinkCollectionExtractor;
use ZF\Hal\Extractor\LinkExtractor;
use ZF\Hal\Metadata\MetadataMap;
use ZF\Hal\Plugin;

class HalViewHelperFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string             $requestedName
     * @param  null|array         $options
     *
     * @return object
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     *     creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = NULL)
    {
        $halConfig       = $container->get('ZF\Hal\HalConfig');
        /* @var $rendererOptions \ZF\Hal\RendererOptions */
        $rendererOptions = $container->get('ZF\Hal\RendererOptions');
        /** @var MetadataMap $metadataMap */
        $metadataMap     = $container->get('ZF\Hal\MetadataMap');
        $hydrators       = $metadataMap->getHydratorManager();

        $serverUrlHelper = $container->get('ViewHelperManager')->get('ServerUrl');
        if (isset($halConfig['options']['use_proxy'])) {
            $serverUrlHelper->setUseProxy($halConfig['options']['use_proxy']);
        }

        $urlHelper = $container->get('ViewHelperManager')->get('Url');

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
