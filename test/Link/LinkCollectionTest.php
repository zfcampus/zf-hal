<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Link;

use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkCollection;
use PHPUnit_Framework_TestCase as TestCase;

class LinkCollectionTest extends TestCase
{
    public function setUp()
    {
        $this->links = new LinkCollection();
    }

    public function testCanAddDiscreteLinkRelations()
    {
        $describedby = new Link('describedby');
        $self = new Link('self');
        $this->links->add($describedby);
        $this->links->add($self);

        $this->assertTrue($this->links->has('describedby'));
        $this->assertSame($describedby, $this->links->get('describedby'));
        $this->assertTrue($this->links->has('self'));
        $this->assertSame($self, $this->links->get('self'));
    }

    public function testCanAddDuplicateLinkRelations()
    {
        $order1 = new Link('order');
        $order2 = new Link('order');

        $this->links->add($order1)
                    ->add($order2);

        $this->assertTrue($this->links->has('order'));
        $orders = $this->links->get('order');
        $this->assertInternalType('array', $orders);
        $this->assertContains($order1, $orders);
        $this->assertContains($order2, $orders);
    }

    public function testCanRemoveLinkRelations()
    {
        $describedby = new Link('describedby');
        $this->links->add($describedby);
        $this->assertTrue($this->links->has('describedby'));
        $this->links->remove('describedby');
        $this->assertFalse($this->links->has('describedby'));
    }

    public function testCanOverwriteLinkRelations()
    {
        $order1 = new Link('order');
        $order2 = new Link('order');

        $this->links->add($order1)
                    ->add($order2, true);

        $this->assertTrue($this->links->has('order'));
        $orders = $this->links->get('order');
        $this->assertSame($order2, $orders);
    }

    public function testCanIterateLinks()
    {
        $describedby = new Link('describedby');
        $self = new Link('self');
        $this->links->add($describedby);
        $this->links->add($self);

        $this->assertEquals(2, $this->links->count());
        $i = 0;
        foreach ($this->links as $link) {
            $this->assertInstanceOf('ZF\Hal\Link\Link', $link);
            $i += 1;
        }
        $this->assertEquals(2, $i);
    }

    public function testCannotDuplicateSelf()
    {
        $first = new Link('self');
        $second = new Link('self');

        $this->links->add($first)
                    ->add($second);

        $this->assertTrue($this->links->has('self'));
        $this->assertInstanceOf('ZF\Hal\Link\Link', $this->links->get('self'));
        $this->assertSame($second, $this->links->get('self'));
    }
}
