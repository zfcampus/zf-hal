<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Extractor;

use PHPUnit_Framework_TestCase as TestCase;
use ZF\Hal\Extractor\LinkCollectionExtractor;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkCollection;

class LinkCollectionExtractorTest extends TestCase
{
    protected $linkCollectionExtractor;

    public function setUp()
    {
        $linkExtractor = $this->getMockBuilder('ZF\Hal\Extractor\LinkExtractor')
            ->disableOriginalConstructor()
            ->getMock();

        $this->linkCollectionExtractor = new LinkCollectionExtractor($linkExtractor);
    }

    public function testExtractGivenLinkCollectionShouldReturnArrayWithExtractionOfEachLink()
    {
        $linkCollection = new LinkCollection();
        $linkCollection->add(Link::factory([
            'rel' => 'foo',
            'url' => 'http://example.com/foo',
        ]));
        $linkCollection->add(Link::factory([
            'rel' => 'bar',
            'url' => 'http://example.com/bar',
        ]));
        $linkCollection->add(Link::factory([
            'rel' => 'baz',
            'url' => 'http://example.com/baz',
        ]));

        $result = $this->linkCollectionExtractor->extract($linkCollection);

        $this->assertInternalType('array', $result);
        $this->assertCount($linkCollection->count(), $result);
    }

    public function testLinkCollectionWithTwoLinksForSameRelationShouldReturnArrayWithOneKeyAggregatingLinks()
    {
        $linkCollection = new LinkCollection();
        $linkCollection->add(Link::factory([
            'rel' => 'foo',
            'url' => 'http://example.com/foo',
        ]));
        $linkCollection->add(Link::factory([
            'rel' => 'foo',
            'url' => 'http://example.com/bar',
        ]));
        $linkCollection->add(Link::factory([
            'rel' => 'baz',
            'url' => 'http://example.com/baz',
        ]));

        $result = $this->linkCollectionExtractor->extract($linkCollection);

        $this->assertInternalType('array', $result);
        $this->assertCount(2, $result);
        $this->assertInternalType('array', $result['foo']);
        $this->assertCount(2, $result['foo']);
    }
}
