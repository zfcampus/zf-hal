<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\View;

use Zend\View\Strategy\JsonStrategy;
use Zend\View\ViewEvent;

/**
 * Extension of the JSON strategy to handle the HalJsonModel and provide
 * a Content-Type header appropriate to the response it describes.
 *
 * This will give the following content types:
 *
 * - application/hal+json for a result that contains HAL-compliant links
 * - application/json for all other responses
 */
class HalJsonStrategy extends JsonStrategy
{
    /**
     * @var string
     */
    protected $contentType = 'application/json';

    /**
     * @param HalJsonRenderer $renderer
     */
    public function __construct(HalJsonRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Detect if we should use the HalJsonRenderer based on model type.
     *
     * @param  ViewEvent $e
     * @return null|HalJsonRenderer
     */
    public function selectRenderer(ViewEvent $e)
    {
        $model = $e->getModel();

        if (!$model instanceof HalJsonModel) {
            // unrecognized model; do nothing
            return;
        }

        // JsonModel found
        $this->renderer->setViewEvent($e);
        return $this->renderer;
    }

    /**
     * Inject the response
     *
     * Injects the response with the rendered content, and sets the content
     * type based on the detection that occurred during renderer selection.
     *
     * @param  ViewEvent $e
     */
    public function injectResponse(ViewEvent $e)
    {
        $renderer = $e->getRenderer();
        if ($renderer !== $this->renderer) {
            // Discovered renderer is not ours; do nothing
            return;
        }

        $result   = $e->getResult();
        if (!is_string($result)) {
            // We don't have a string, and thus, no JSON
            return;
        }

        $model       = $e->getModel();
        $contentType = $this->contentType;
        $response    = $e->getResponse();

        if ($model instanceof HalJsonModel
            && ($model->isCollection() || $model->isEntity())
        ) {
            $contentType = 'application/hal+json';
        }

        // Populate response
        $response->setContent($result);
        $headers = $response->getHeaders();
        $headers->addHeaderLine('content-type', $contentType);
    }
}
