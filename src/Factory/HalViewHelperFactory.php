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
        $config          = $services->get('Config');
        $metadataMap     = $services->get('ZF\Hal\MetadataMap');
        $hydrators       = $metadataMap->getHydratorManager();

        $serverUrlHelper = $serviceLocator->get('ServerUrl');
        if (isset($config['zf-hal']['options']['use_proxy'])) {
            $serverUrlHelper->setUseProxy($config['zf-hal']['options']['use_proxy']);
        }
        $urlHelper       = $serviceLocator->get('Url');

        $helper = new Plugin\Hal($hydrators);
        $helper
            ->setMetadataMap($metadataMap)
            ->setServerUrlHelper($serverUrlHelper)
            ->setUrlHelper($urlHelper);

        if (isset($config['zf-hal'])
            && isset($config['zf-hal']['renderer'])
        ) {
            $config = $config['zf-hal']['renderer'];

            if (isset($config['default_hydrator'])) {
                $hydratorServiceName = $config['default_hydrator'];

                if (!$hydrators->has($hydratorServiceName)) {
                    throw new Exception\DomainException(sprintf(
                        'Cannot locate default hydrator by name "%s" via the HydratorManager',
                        $hydratorServiceName
                    ));
                }

                $hydrator = $hydrators->get($hydratorServiceName);
                $helper->setDefaultHydrator($hydrator);
            }

            if (isset($config['render_embedded_resources'])) {
                $helper->setRenderEmbeddedEntities($config['render_embedded_resources']);
            }

            if (isset($config['render_embedded_entities'])) {
                $helper->setRenderEmbeddedEntities($config['render_embedded_entities']);
            }

            if (isset($config['render_collections'])) {
                $helper->setRenderCollections($config['render_collections']);
            }

            if (isset($config['hydrators']) && is_array($config['hydrators'])) {
                $hydratorMap = $config['hydrators'];
                foreach ($hydratorMap as $class => $hydratorServiceName) {
                    $helper->addHydrator($class, $hydratorServiceName);
                }
            }
        }

        return $helper;
    }
}
