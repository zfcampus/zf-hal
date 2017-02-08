<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2017 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal;

use PHPUnit\Framework\TestCase;
use stdClass;
use ZF\Hal\Entity;
use ZF\Hal\Exception\InvalidEntityException;
use ZF\Hal\Link\LinkCollection;

class EntityTest extends TestCase
{
    public function invalidEntities()
    {
        return [
            'null'       => [null],
            'true'       => [true],
            'false'      => [false],
            'zero-int'   => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'string'     => ['string'],
        ];
    }

    /**
     * @dataProvider invalidEntities
     *
     * @param mixed $entity
     */
    public function testConstructorRaisesExceptionForNonObjectNonArrayEntity($entity)
    {
        $this->expectException(InvalidEntityException::class);

        new Entity($entity, 'id');
    }

    public function testPropertiesAreAccessibleAfterConstruction()
    {
        $entity = new stdClass;
        $hal    = new Entity($entity, 'id');

        $this->assertSame($entity, $hal->getEntity());
        $this->assertEquals('id', $hal->getId());
    }

    public function testComposesLinkCollectionByDefault()
    {
        $entity = new stdClass;
        $hal    = new Entity($entity, 'id', 'route', ['foo' => 'bar']);

        $this->assertInstanceOf(LinkCollection::class, $hal->getLinks());
    }

    public function testLinkCollectionMayBeInjected()
    {
        $entity = new stdClass;
        $hal    = new Entity($entity, 'id', 'route', ['foo' => 'bar']);
        $links  = new LinkCollection();
        $hal->setLinks($links);

        $this->assertSame($links, $hal->getLinks());
    }

    public function testRetrievingEntityCanReturnByReference()
    {
        $entity = ['foo' => 'bar'];
        $hal    = new Entity($entity, 'id');
        $this->assertEquals($entity, $hal->getEntity());

        $entity =& $hal->getEntity();
        $entity['foo'] = 'baz';

        $secondRetrieval =& $hal->getEntity();
        $this->assertEquals('baz', $secondRetrieval['foo']);
    }

    /**
     * @group 39
     */
    public function testConstructorAllowsNullIdentifier()
    {
        $hal = new Entity(['foo' => 'bar'], null);
        $this->assertNull($hal->getId());
    }

    public function magicProperties()
    {
        return [
            'entity' => ['entity'],
            'id'     => ['id'],
        ];
    }

    /**
     * @group 99
     * @dataProvider magicProperties
     */
    public function testPropertyRetrievalEmitsDeprecationNotice($property)
    {
        $entity    = ['foo' => 'bar'];
        $hal       = new Entity($entity, 'id');
        $triggered = false;

        set_error_handler(function ($errno, $errstr) use (&$triggered) {
            $triggered = true;
            $this->assertContains('Direct property access', $errstr);
        }, E_USER_DEPRECATED);
        $hal->$property;
        restore_error_handler();

        $this->assertTrue($triggered, 'Deprecation notice was not triggered!');
    }
}
