<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZFTest\Hal\TestAsset;

/**
 * @subpackage UnitTest
 */
class ArraySerializable
{
    public function getHijinx()
    {
        return 'should not get this';
    }

    public function getArrayCopy()
    {
        return array('foo' => 'bar');
    }
}
