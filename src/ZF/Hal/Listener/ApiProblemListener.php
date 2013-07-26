<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZF\Hal\Listener;

use ZF\Hal\ApiProblem;
use ZF\Hal\View\ApiProblemModel;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\AbstractListenerAggregate;
use Zend\Http\Request as HttpRequest;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ModelInterface;

/**
 * ApiProblemListener
 *
 * Provides a listener on the render event, at high priority.
 *
 * If the MvcEvent represents an error, then its view model and result are
 * replaced with a RestfulJsonModel containing an API-Problem payload.
 */
class ApiProblemListener extends AbstractListenerAggregate
{
    /**
     * Default values to match in Accept header
     *
     * @var string
     */
    protected static $acceptFilter = 'application/json';

    /**
     * Constructor
     *
     * Set the accept filter, if one is passed
     *
     * @param string $filter
     */
    public function __construct($filter = null)
    {
        if (is_string($filter) && !empty($filter)) {
            self::$acceptFilter = $filter;
        }
    }

    /**
     * @param EventManagerInterface $events
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_RENDER, __CLASS__ . '::onRender', 1000);
    }

    /**
     * Listen to the render event
     *
     * @param MvcEvent $e
     */
    public static function onRender(MvcEvent $e)
    {
        // only worried about error pages
        if (!$e->isError()) {
            return;
        }

        // and then, only if we have an Accept header...
        $request = $e->getRequest();
        if (!$request instanceof HttpRequest) {
            return;
        }

        $headers = $request->getHeaders();
        if (!$headers->has('Accept')) {
            return;
        }

        // ... that matches certain criteria
        $accept = $headers->get('Accept');
        $match  = $accept->match(self::$acceptFilter);
        if (!$match || $match->getTypeString() == '*/*') {
            return;
        }

        // Next, do we have a view model in the result?
        // If not, nothing more to do.
        $model = $e->getResult();
        if (!$model instanceof ModelInterface) {
            return;
        }

        // Marshal the information we need for the API-Problem response
        $httpStatus = $e->getResponse()->getStatusCode();
        $exception  = $model->getVariable('exception');

        if ($exception instanceof \Exception) {
            $apiProblem = new ApiProblem($httpStatus, $exception);
        } else {
            $apiProblem = new ApiProblem($httpStatus, $model->getVariable('message'));
        }

        // Create a new model with the API-Problem payload, and reset
        // the result and view model in the event using it.
        $model = new ApiProblemModel($apiProblem);
        $e->setResult($model);
        $e->setViewModel($model);
    }
}
