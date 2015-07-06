<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Extractor;

use ZF\Hal\Link\LinkCollection;

interface LinkCollectionExtractorInterface
{
    /**
     * Extract a link collection into a structured set of links.
     *
     * @param LinkCollection $collection
     * @return array
     */
    public function extract(LinkCollection $collection);

    /**
     * @return LinkExtractorInterface
     */
    public function getLinkExtractor();
}
