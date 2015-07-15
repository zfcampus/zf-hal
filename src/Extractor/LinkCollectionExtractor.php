<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Extractor;

use ZF\ApiProblem\Exception\DomainException;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkCollection;

class LinkCollectionExtractor implements LinkCollectionExtractorInterface
{
    /**
     * @var LinkExtractorInterface
     */
    protected $linkExtractor;

    /**
     * @param LinkExtractorInterface $linkExtractor
     */
    public function __construct(LinkExtractorInterface $linkExtractor)
    {
        $this->setLinkExtractor($linkExtractor);
    }

    /**
     * @return LinkExtractorInterface
     */
    public function getLinkExtractor()
    {
        return $this->linkExtractor;
    }

    /**
     * @param LinkExtractorInterface $linkExtractor
     */
    public function setLinkExtractor(LinkExtractorInterface $linkExtractor)
    {
        $this->linkExtractor = $linkExtractor;
    }

    /**
     * @inheritDoc
     */
    public function extract(LinkCollection $collection)
    {
        $links = [];
        foreach ($collection as $rel => $linkDefinition) {
            if ($linkDefinition instanceof Link) {
                $links[$rel] = $this->linkExtractor->extract($linkDefinition);
                continue;
            }

            if (!is_array($linkDefinition)) {
                throw new DomainException(sprintf(
                    'Link object for relation "%s" in resource was malformed; cannot generate link',
                    $rel
                ));
            }

            $aggregate = [];
            foreach ($linkDefinition as $subLink) {
                if (!$subLink instanceof Link) {
                    throw new DomainException(sprintf(
                        'Link object aggregated for relation "%s" in resource was malformed; cannot generate link',
                        $rel
                    ));
                }

                $aggregate[] = $this->linkExtractor->extract($subLink);
            }

            $links[$rel] = $aggregate;
        }

        return $links;
    }
}
