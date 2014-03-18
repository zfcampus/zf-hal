<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\View;

use Zend\Paginator\Paginator;
use Zend\Stdlib\Hydrator\HydratorInterface;
use Zend\View\HelperPluginManager;
use Zend\View\Renderer\JsonRenderer;
use Zend\View\ViewEvent;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\View\ApiProblemModel;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\Hal\Entity;
use ZF\Hal\Collection;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkCollection;
use ZF\Hal\Plugin\Hal as HalPlugin;

/**
 * Handles rendering of the following:
 *
 * - API-Problem
 * - HAL collections
 * - HAL resources
 */
class HalJsonRenderer extends JsonRenderer
{
    /**
     * @var ApiProblemRenderer
     */
    protected $apiProblemRenderer;

    /**
     * @var HelperPluginManager
     */
    protected $helpers;

    /**
     * @var ViewEvent
     */
    protected $viewEvent;

    /**
     * @param ApiProblemRenderer $apiProblemRenderer
     */
    public function __construct(ApiProblemRenderer $apiProblemRenderer)
    {
        $this->apiProblemRenderer = $apiProblemRenderer;
    }

    /**
     * Set helper plugin manager instance.
     *
     * Also ensures that the 'Hal' helper is present.
     *
     * @param  HelperPluginManager $helpers
     */
    public function setHelperPluginManager(HelperPluginManager $helpers)
    {
        if (!$helpers->has('Hal')) {
            $this->injectHalHelper($helpers);
        }
        $this->helpers = $helpers;
    }

    /**
     * @param  ViewEvent $event
     * @return self
     */
    public function setViewEvent(ViewEvent $event)
    {
        $this->viewEvent = $event;
        return $this;
    }

    /**
     * Lazy-loads a helper plugin manager if none available.
     *
     * @return HelperPluginManager
     */
    public function getHelperPluginManager()
    {
        if (!$this->helpers instanceof HelperPluginManager) {
            $this->setHelperPluginManager(new HelperPluginManager());
        }
        return $this->helpers;
    }

    /**
     * @return ViewEvent
     */
    public function getViewEvent()
    {
        return $this->viewEvent;
    }

    /**
     * Render a view model
     *
     * If the view model is a HalJsonRenderer, determines if it represents
     * a Collection or Entity, and, if so, creates a custom
     * representation appropriate to the type.
     *
     * If not, it passes control to the parent to render.
     *
     * @param  mixed $nameOrModel
     * @param  mixed $values
     * @return string
     */
    public function render($nameOrModel, $values = null)
    {
        if (!$nameOrModel instanceof HalJsonModel) {
            return parent::render($nameOrModel, $values);
        }

        if ($nameOrModel->isEntity()) {
            $helper  = $this->helpers->get('Hal');
            $payload = $helper->renderEntity($nameOrModel->getPayload());
            return parent::render($payload);
        }

        if ($nameOrModel->isCollection()) {
            $helper  = $this->helpers->get('Hal');
            $payload = $helper->renderCollection($nameOrModel->getPayload());

            if ($payload instanceof ApiProblem) {
                return $this->renderApiProblem($payload);
            }
            return parent::render($payload);
        }

        return parent::render($nameOrModel, $values);
    }

    /**
     * Inject the helper manager with the Hal helper
     *
     * @param  HelperPluginManager $helpers
     */
    protected function injectHalHelper(HelperPluginManager $helpers)
    {
        $helper = new HalHelper();
        $helper->setView($this);
        $helper->setServerUrlHelper($helpers->get('ServerUrl'));
        $helper->setUrlHelper($helpers->get('Url'));
        $helpers->setService('Hal', $helper);
    }

    /**
     * Render an API-Problem result
     *
     * Creates an ApiProblemModel with the provided ApiProblem, and passes it
     * on to the composed ApiProblemRenderer to render.
     *
     * If a ViewEvent is composed, it passes the ApiProblemModel to it so that
     * the ApiProblemStrategy can be invoked when populating the response.
     *
     * @param  ApiProblem $problem
     * @return string
     */
    protected function renderApiProblem(ApiProblem $problem)
    {
        $model = new ApiProblemModel($problem);
        $event = $this->getViewEvent();
        if ($event) {
            $event->setModel($model);
        }
        return $this->apiProblemRenderer->render($model);
    }
}
