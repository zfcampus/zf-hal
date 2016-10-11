<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2016 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal;

use Zend\Mvc\ApplicationInterface;
use Zend\Mvc\MvcEvent;
use ZF\Hal\View\HalJsonStrategy;

class Module
{
    /**
     * Retrieve module configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    /**
     * Listener for bootstrap event
     *
     * Attaches a render event.
     *
     * @param  MvcEvent $e
     */
    public function onBootstrap(MvcEvent $e)
    {
        /** @var ApplicationInterface $application */
        $application = $e->getTarget();
        $events = $application->getEventManager();
        $events->attach(MvcEvent::EVENT_RENDER, [$this, 'onRender'], 100);
    }

    /**
     * Listener for the render event
     *
     * Attaches a rendering/response strategy to the View.
     *
     * @param  MvcEvent $e
     */
    public function onRender(MvcEvent $e)
    {
        $result = $e->getResult();
        if (! $result instanceof View\HalJsonModel) {
            return;
        }

        /** @var Application $application */
        $application = $e->getTarget();
        $services = $application->getServiceManager();
        $events   = $services->get('View')->getEventManager();

        // register at high priority, to "beat" normal json strategy registered
        // via view manager
        /** @var HalJsonStrategy $halStrategy */
        $halStrategy = $services->get('ZF\Hal\JsonStrategy');
        $halStrategy->attach($events, 200);
    }
}
