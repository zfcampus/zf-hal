<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2017 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\View;

use PHPUnit\Framework\TestCase;
use Zend\Http\Response;
use Zend\View\ViewEvent;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\View\ApiProblemModel;
use ZF\ApiProblem\View\ApiProblemRenderer;
use ZF\Hal\Collection;
use ZF\Hal\Entity;
use ZF\Hal\Link\Link;
use ZF\Hal\View\HalJsonModel;
use ZF\Hal\View\HalJsonRenderer;
use ZF\Hal\View\HalJsonStrategy;

/**
 * @subpackage UnitTest
 */
class HalJsonStrategyTest extends TestCase
{
    /**
     * @var ViewEvent
     */
    protected $event;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var HalJsonRenderer
     */
    protected $renderer;

    /**
     * @var HalJsonStrategy
     */
    protected $strategy;

    public function setUp()
    {
        $this->response = new Response;
        $this->event    = new ViewEvent;
        $this->event->setResponse($this->response);

        $this->renderer = new HalJsonRenderer(new ApiProblemRenderer());
        $this->strategy = new HalJsonStrategy($this->renderer);
    }

    public function testSelectRendererReturnsNullIfModelIsNotAHalJsonModel()
    {
        $this->assertNull($this->strategy->selectRenderer($this->event));
    }

    public function testSelectRendererReturnsRendererIfModelIsAHalJsonModel()
    {
        $model = new HalJsonModel();
        $this->event->setModel($model);
        $this->assertSame($this->renderer, $this->strategy->selectRenderer($this->event));
    }

    public function testInjectResponseDoesNotSetContentTypeHeaderIfRendererDoesNotMatch()
    {
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertFalse($headers->has('Content-Type'));
    }

    public function testInjectResponseDoesNotSetContentTypeHeaderIfResultIsNotString()
    {
        $this->event->setRenderer($this->renderer);
        $this->event->setResult(['foo']);
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertFalse($headers->has('Content-Type'));
    }

    public function testInjectResponseSetsContentTypeHeaderToDefaultIfNotHalModel()
    {
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/json', $header->getFieldValue());
    }

    public function halObjects()
    {
        $entity = new Entity([
            'foo' => 'bar',
        ], 'identifier', 'route');
        $link = new Link('self');
        $link->setRoute('resource/route')->setRouteParams(['id' => 'identifier']);
        $entity->getLinks()->add($link);

        $collection = new Collection([$entity]);
        $collection->setCollectionRoute('collection/route');
        $collection->setEntityRoute('resource/route');

        return [
            'entity'     => [$entity],
            'collection' => [$collection],
        ];
    }

    /**
     * @dataProvider halObjects
     */
    public function testInjectResponseSetsContentTypeHeaderToHalForHalModel($hal)
    {
        $model = new HalJsonModel(['payload' => $hal]);

        $this->event->setModel($model);
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/hal+json', $header->getFieldValue());
    }

    public function testInjectResponseSetsContentTypeHeaderToApiProblemForApiProblemModel()
    {
        $problem = new ApiProblem(500, "Error message");
        $model = new ApiProblemModel($problem);

        $this->event->setModel($model);
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/problem+json', $header->getFieldValue());
    }
}
