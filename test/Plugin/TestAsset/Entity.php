<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Plugin\TestAsset;

class Entity
{
    public $id;
    public $name;

    public $first_child;
    public $second_child;

    protected $doNotExportMe = "some secret data";

    public function __construct($id, $name)
    {
        $this->id   = $id;
        $this->name = $name;
    }
}
