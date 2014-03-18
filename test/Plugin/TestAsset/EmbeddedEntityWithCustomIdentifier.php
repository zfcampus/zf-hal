<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Plugin\TestAsset;

class EmbeddedEntityWithCustomIdentifier
{
    public $custom_id;
    public $name;

    public function __construct($id, $name)
    {
        $this->custom_id = $id;
        $this->name      = $name;
    }
}
