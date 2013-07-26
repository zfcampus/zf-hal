<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZF\Hal\View;

use ZF\Hal\ApiProblem;
use ZF\Hal\HalCollection;
use ZF\Hal\HalResource;
use ZF\Hal\Link;
use ZF\Hal\LinkCollection;
use ZF\Hal\Plugin\HalLinks;
use Zend\Paginator\Paginator;
use Zend\Stdlib\Hydrator\HydratorInterface;
use Zend\View\HelperPluginManager;
use Zend\View\Renderer\JsonRenderer;
use Zend\View\ViewEvent;

/**
 * Handles rendering of the following:
 *
 * - API-Problem
 * - HAL collections
 * - HAL resources
 */
class RestfulJsonRenderer extends JsonRenderer
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
     * Also ensures that the 'HalLinks' helper is present.
     *
     * @param  HelperPluginManager $helpers
     */
    public function setHelperPluginManager(HelperPluginManager $helpers)
    {
        if (!$helpers->has('HalLinks')) {
            $this->injectHalLinksHelper($helpers);
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
     * If the view model is a RestfulJsonRenderer, determines if it represents
     * an ApiProblem, HalCollection, or HalResource, and, if so, creates a custom
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
        if (!$nameOrModel instanceof RestfulJsonModel) {
            return parent::render($nameOrModel, $values);
        }

        if ($nameOrModel->isHalResource()) {
            $helper  = $this->helpers->get('HalLinks');
            $payload = $helper->renderResource($nameOrModel->getPayload());
            return parent::render($payload);
        }

        if ($nameOrModel->isHalCollection()) {
            $helper  = $this->helpers->get('HalLinks');
            $payload = $helper->renderCollection($nameOrModel->getPayload());

            if ($payload instanceof ApiProblem) {
                return $this->renderApiProblem($payload);
            }
            return parent::render($payload);
        }

        return parent::render($nameOrModel, $values);
    }

    /**
     * Inject the helper manager with the HalLinks helper
     *
     * @param  HelperPluginManager $helpers
     */
    protected function injectHalLinksHelper(HelperPluginManager $helpers)
    {
        $helper = new HalLinks();
        $helper->setView($this);
        $helper->setServerUrlHelper($helpers->get('ServerUrl'));
        $helper->setUrlHelper($helpers->get('Url'));
        $helpers->setService('HalLinks', $helper);
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
