<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZFTest\Hal\View;

use ZF\Hal\HalCollection;
use ZF\Hal\HalResource;
use ZF\Hal\Link;
use ZF\Hal\View\ApiProblemRenderer;
use ZF\Hal\View\RestfulJsonModel;
use ZF\Hal\View\RestfulJsonRenderer;
use ZF\Hal\View\RestfulJsonStrategy;
use PHPUnit_Framework_TestCase as TestCase;
use Zend\Http\Response;
use Zend\View\Renderer\JsonRenderer;
use Zend\View\ViewEvent;

/**
 * @subpackage UnitTest
 */
class RestfulJsonStrategyTest extends TestCase
{
    public function setUp()
    {
        $this->response = new Response;
        $this->event    = new ViewEvent;
        $this->event->setResponse($this->response);

        $this->renderer = new RestfulJsonRenderer(new ApiProblemRenderer());
        $this->strategy = new RestfulJsonStrategy($this->renderer);
    }

    public function testSelectRendererReturnsNullIfModelIsNotARestfulJsonModel()
    {
        $this->assertNull($this->strategy->selectRenderer($this->event));
    }

    public function testSelectRendererReturnsRendererIfModelIsARestfulJsonModel()
    {
        $model = new RestfulJsonModel();
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
        $this->event->setResult(array('foo'));
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
        $resource = new HalResource(array(
            'foo' => 'bar',
        ), 'identifier', 'route');
        $link = new Link('self');
        $link->setRoute('resource/route')->setRouteParams(array('id' => 'identifier'));
        $resource->getLinks()->add($link);

        $collection = new HalCollection(array($resource));
        $collection->setCollectionRoute('collection/route');
        $collection->setResourceRoute('resource/route');

        return array(
            'resource'   => array($resource),
            'collection' => array($collection),
        );
    }

    /**
     * @dataProvider halObjects
     */
    public function testInjectResponseSetsContentTypeHeaderToHalForHalModel($hal)
    {
        $model = new RestfulJsonModel(array('payload' => $hal));

        $this->event->setModel($model);
        $this->event->setRenderer($this->renderer);
        $this->event->setResult('{"foo":"bar"}');
        $this->strategy->injectResponse($this->event);
        $headers = $this->response->getHeaders();
        $this->assertTrue($headers->has('Content-Type'));
        $header = $headers->get('Content-Type');
        $this->assertEquals('application/hal+json', $header->getFieldValue());
    }
}
