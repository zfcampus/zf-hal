<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2016 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\TestAsset;

/**
 * See ZFTest\Hal\ResourceFactoryTest::testRouteParamsAllowsCallable
 */
class EntityDefiningCallback
{
    private $expected;
    private $phpunit;

    public function __construct($phpunit, $expected)
    {
        $this->phpunit  = $phpunit;
        $this->expected = $expected;
    }

    public function callback($value)
    {
        $this->phpunit->assertSame($this->expected, $value);
        return 'callback-param';
    }
}
