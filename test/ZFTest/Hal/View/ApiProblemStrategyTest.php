<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZFTest\Hal\View;

use ZF\Hal\ApiProblem;
use ZF\Hal\View\ApiProblemModel;
use ZF\Hal\View\ApiProblemRenderer;
use ZF\Hal\View\ApiProblemStrategy;
use ZF\Hal\View\RestfulJsonModel;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Http\Response;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Zend\View\Renderer\JsonRenderer;
use Zend\View\ViewEvent;

/**
 * @subpackage UnitTest
 */
class ApiProblemStrategyTest extends TestCase
{
    public function setUp()
    {
        $this->response = new Response;
        $this->event    = new ViewEvent;
        $this->event->setResponse($this->response);

        $this->renderer = new ApiProblemRenderer;
        $this->strategy = new ApiProblemStrategy($this->renderer);
    }

    public function invalidViewModels()
    {
        return array(
            'null'    => array(null),
            'generic' => array(new ViewModel()),
            'json'    => array(new JsonModel()),
            'hal'     => array(new RestfulJsonModel()),
        );
    }

    /**
     * @dataProvider invalidViewModels
     */
    public function testSelectRendererReturnsNullIfModelIsNotAnApiProblemModel($model)
    {
        if (null !== $model) {
            $this->event->setModel($model);
        }
        $this->assertNull($this->strategy->selectRenderer($this->event));
    }

    public function testSelectRendererReturnsRendererIfModelIsAnApiProblemModel()
    {
        $model = new ApiProblemModel();
        $this->event->setModel($model);
        $this->assertSame($this->renderer, $this->strategy->selectRenderer($this->event));
    }

    public function testInjectResponseDoesNotSetContentTypeHeaderIfResultIsNotString()
    {
        $this->event->setRenderer($this->renderer);
        $this->event->setResult(array('foo'));
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertFalse($headers->has('Content-Type'));
    }

    public function testInjectResponseSetsContentTypeHeaderToApiProblemForApiProblemModel()
    {
        $problem = new ApiProblem(500, 'whatever', 'foo', 'bar');
        $model   = new ApiProblemModel($problem);
        $this->event->setModel($model);
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/api-problem+json', $header->getFieldValue());
    }

    public function invalidStatusCodes()
    {
        return array(
            array(0),
            array(1),
            array(99),
            array(600),
            array(10081),
        );
    }

    /**
     * @dataProvider invalidStatusCodes
     */
    public function testUsesStatusCode500ForAnyStatusCodesAbove599OrBelow100($status)
    {
        $problem = new ApiProblem($status, 'whatever');
        $model   = new ApiProblemModel($problem);
        $this->event->setModel($model);
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);

        $this->assertEquals(500, $this->response->getStatusCode());
    }
}
