<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Extractor;

use Zend\Stdlib\Extractor\ExtractionInterface;

interface LinkCollectionExtractorInterface extends ExtractionInterface
{
    /**
     * @return LinkExtractorInterface
     */
    public function getLinkExtractor();

    /**
     * @param LinkExtractorInterface $linkExtractor
     */
    public function setLinkExtractor(LinkExtractorInterface $linkExtractor);
}
