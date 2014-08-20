<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal;

use Zend\Stdlib\Hydrator\HydratorPluginManager;
use Zend\Mvc\MvcEvent;

/**
 * ZF2 module
 */
class Module
{
    /**
     * Retrieve autoloader configuration
     *
     * @return array
     */
    public function getAutoloaderConfig()
    {
        return array('Zend\Loader\StandardAutoloader' => array('namespaces' => array(
            __NAMESPACE__ => __DIR__ . '/src/',
        )));
    }

    /**
     * Retrieve module configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Retrieve Service Manager configuration
     *
     * Defines ZF\Hal\JsonStrategy service factory.
     *
     * @return array
     */
    public function getServiceConfig()
    {
        return array('factories' => array(
            'ZF\Hal\MetadataMap' => function ($services) {
                $config = array();
                if ($services->has('config')) {
                    $config = $services->get('config');
                }

                if ($services->has('HydratorManager')) {
                    $hydrators = $services->get('HydratorManager');
                } else {
                    $hydrators = new HydratorPluginManager();
                }

                $map = array();
                if (isset($config['zf-hal'])
                    && isset($config['zf-hal']['metadata_map'])
                    && is_array($config['zf-hal']['metadata_map'])
                ) {
                    $map = $config['zf-hal']['metadata_map'];
                }

                return new Metadata\MetadataMap($map, $hydrators);
            },
            'ZF\Hal\JsonRenderer' => function ($services) {
                $helpers            = $services->get('ViewHelperManager');
                $apiProblemRenderer = $services->get('ZF\ApiProblem\ApiProblemRenderer');

                $renderer = new View\HalJsonRenderer($apiProblemRenderer);
                $renderer->setHelperPluginManager($helpers);

                return $renderer;
            },
            'ZF\Hal\JsonStrategy' => function ($services) {
                $renderer = $services->get('ZF\Hal\JsonRenderer');
                return new View\HalJsonStrategy($renderer);
            },
        ));
    }

    /**
     * Define factories for controller plugins
     *
     * Defines the "Hal" plugin.
     *
     * @return array
     */
    public function getControllerPluginConfig()
    {
        return array('factories' => array(
            'Hal' => function ($plugins) {
                $services = $plugins->getServiceLocator();
                $helpers  = $services->get('ViewHelperManager');
                return $helpers->get('Hal');
            },
        ));
    }

    /**
     * Defines the "Hal" view helper
     *
     * @return array
     */
    public function getViewHelperConfig()
    {
        return array('factories' => array(
            'Hal' => function ($helpers) {
                $serverUrlHelper = $helpers->get('ServerUrl');
                $urlHelper       = $helpers->get('Url');

                $services        = $helpers->getServiceLocator();
                $config          = $services->get('Config');
                $metadataMap     = $services->get('ZF\Hal\MetadataMap');
                $hydrators       = $metadataMap->getHydratorManager();

                $helper          = new Plugin\Hal($hydrators);
                $helper->setMetadataMap($metadataMap);
                $helper->setServerUrlHelper($serverUrlHelper);
                $helper->setUrlHelper($urlHelper);

                if (isset($config['zf-hal'])
                    && isset($config['zf-hal']['renderer'])
                ) {
                    $config = $config['zf-hal']['renderer'];

                    if (isset($config['default_hydrator'])) {
                        $hydratorServiceName = $config['default_hydrator'];

                        if (!$hydrators->has($hydratorServiceName)) {
                            throw new Exception\DomainException(
                                sprintf(
                                    'Cannot locate default hydrator by name "%s" via the HydratorManager',
                                    $hydratorServiceName
                                )
                            );
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
        ));
    }

    /**
     * Listener for bootstrap event
     *
     * Attaches a render event.
     *
     * @param  \Zend\Mvc\MvcEvent $e
     */
    public function onBootstrap($e)
    {
        $app      = $e->getTarget();
        $services = $app->getServiceManager();
        $events   = $app->getEventManager();
        $events->attach(MvcEvent::EVENT_RENDER, array($this, 'onRender'), 100);
    }

    /**
     * Listener for the render event
     *
     * Attaches a rendering/response strategy to the View.
     *
     * @param  \Zend\Mvc\MvcEvent $e
     */
    public function onRender($e)
    {
        $result = $e->getResult();
        if (!$result instanceof View\HalJsonModel) {
            return;
        }

        $app                 = $e->getTarget();
        $services            = $app->getServiceManager();
        $view                = $services->get('View');
        $events              = $view->getEventManager();

        // register at high priority, to "beat" normal json strategy registered
        // via view manager
        $events->attach($services->get('ZF\Hal\JsonStrategy'), 200);
    }
}
