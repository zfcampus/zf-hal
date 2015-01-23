<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Plugin\TestAsset;

class EmbeddedEntityWithBackReference
{
    public $id;
    public $parent;

    public function __construct($id, Entity $parent)
    {
        $this->id   = $id;
        $this->parent = $parent;
    }
}
