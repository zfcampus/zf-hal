<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Extractor;

use ZF\Hal\Link\Link;

interface LinkExtractorInterface
{
    /**
     * Extract a structured link array from a Link instance.
     *
     * @param Link $link
     * @return array
     */
    public function extract(Link $link);
}
