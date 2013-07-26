<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZFTest\Hal\View;

use ZF\Hal\ApiProblem;
use ZF\Hal\View\ApiProblemModel;
use ZF\Hal\View\ApiProblemRenderer;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * @subpackage UnitTest
 */
class ApiProblemRendererTest extends TestCase
{
    public function setUp()
    {
        $this->renderer = new ApiProblemRenderer();
    }

    public function testRendersApiProblemCorrectly()
    {
        $apiProblem = new ApiProblem(401, 'login error', 'http://status.dev/errors.md', 'Unauthorized');
        $model      = new ApiProblemModel();
        $model->setApiProblem($apiProblem);
        $test = $this->renderer->render($model);
        $expected = array(
            'httpStatus'  => 401,
            'describedBy' => 'http://status.dev/errors.md',
            'title'       => 'Unauthorized',
            'detail'      => 'login error',
        );
        $this->assertEquals($expected, json_decode($test, true));
    }

    public function testCanHintToApiProblemToRenderStackTrace()
    {
        $exception  = new \Exception('exception message', 500);
        $apiProblem = new ApiProblem(500, $exception);
        $model      = new ApiProblemModel();
        $model->setApiProblem($apiProblem);
        $this->renderer->setDisplayExceptions(true);
        $test = $this->renderer->render($model);
        $test = json_decode($test, true);
        $this->assertContains($exception->getMessage() . "\n" . $exception->getTraceAsString(), $test['detail']);
    }
}
