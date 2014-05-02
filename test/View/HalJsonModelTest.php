<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\View;

use PHPUnit_Framework_TestCase as TestCase;
use stdClass;
use ZF\Hal\Collection;
use ZF\Hal\Entity;
use ZF\Hal\View\HalJsonModel;

/**
 * @subpackage UnitTest
 */
class HalJsonModelTest extends TestCase
{
    public function setUp()
    {
        $this->model = new HalJsonModel;
    }

    public function testPayloadIsNullByDefault()
    {
        $this->assertNull($this->model->getPayload());
    }

    public function testPayloadIsMutable()
    {
        $this->model->setPayload('foo');
        $this->assertEquals('foo', $this->model->getPayload());
    }

    public function invalidPayloads()
    {
        return array(
            'null'       => array(null),
            'true'       => array(true),
            'false'      => array(false),
            'zero-int'   => array(0),
            'int'        => array(1),
            'zero-float' => array(0.0),
            'float'      => array(1.1),
            'string'     => array('string'),
            'array'      => array(array()),
            'stdclass'   => array(new stdClass),
        );
    }

    public function invalidCollectionPayloads()
    {
        $payloads = $this->invalidPayloads();
        $payloads['exception'] = array(new \Exception);
        $payloads['stdclass']  = array(new stdClass);
        $payloads['hal-item']  = array(new Entity(array(), 'id', 'route'));
        return $payloads;
    }

    /**
     * @dataProvider invalidCollectionPayloads
     */
    public function testIsCollectionReturnsFalseForInvalidValues($payload)
    {
        $this->model->setPayload($payload);
        $this->assertFalse($this->model->isCollection());
    }

    public function testIsCollectionReturnsTrueForCollectionPayload()
    {
        $collection = new Collection(array(), 'item/route');
        $this->model->setPayload($collection);
        $this->assertTrue($this->model->isCollection());
    }

    public function invalidEntityPayloads()
    {
        $payloads = $this->invalidPayloads();
        $payloads['exception']      = array(new \Exception);
        $payloads['stdclass']       = array(new stdClass);
        $payloads['hal-collection'] = array(new Collection(array(), 'item/route'));
        return $payloads;
    }

    /**
     * @dataProvider invalidEntityPayloads
     */
    public function testIsEntityReturnsFalseForInvalidValues($payload)
    {
        $this->model->setPayload($payload);
        $this->assertFalse($this->model->isEntity());
    }

    public function testIsEntityReturnsTrueForEntityPayload()
    {
        $item = new Entity(array(), 'id', 'route');
        $this->model->setPayload($item);
        $this->assertTrue($this->model->isEntity());
    }

    public function testIsTerminalByDefault()
    {
        $this->assertTrue($this->model->terminate());
    }

    /**
     * @depends testIsTerminalByDefault
     */
    public function testTerminalFlagIsNotMutable()
    {
        $this->model->setTerminal(false);
        $this->assertTrue($this->model->terminate());
    }
}
