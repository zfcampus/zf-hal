<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Link;

interface LinkCollectionAwareInterface
{
    public function setLinks(LinkCollection $links);
    public function getLinks();
}
