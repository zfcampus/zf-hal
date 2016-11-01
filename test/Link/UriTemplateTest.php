<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Link;

use ZF\Hal\Link\UriTemplate;
use PHPUnit_Framework_TestCase as TestCase;

class UriTemplateTest extends TestCase
{
    public function testReturnsFormattedQueryStringWhenGettingFormattedStringForUriTemplateWithQueryParameters()
    {
        $uriTemplate = UriTemplate::withQueryParameters(['id', 'tmp']);

        $this->assertSame('{?id,tmp}', $uriTemplate->getFormattedString());
    }

    public function testReturnsFormattedPathSegmentStringWhenGettingFormattedStringForUriTemplateWithPathSegmentParameters()
    {
        $uriTemplate = UriTemplate::withPathSegmentParameters(['id', 'tmp']);

        $this->assertSame('/{id}/{tmp}', $uriTemplate->getFormattedString());
    }

}
