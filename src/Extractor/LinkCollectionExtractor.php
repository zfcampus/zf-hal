<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Extractor;

use ZF\ApiProblem\Exception\DomainException;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkCollection;

class LinkCollectionExtractor extends LinkExtractor
{
    /**
     * {@inheritDoc}
     */
    public function extract($object)
    {
        if (!$object instanceof LinkCollection) {
            return array();
        }

        $links = array();
        foreach ($object as $rel => $linkDefinition) {
            if ($linkDefinition instanceof Link) {
                $links[$rel] = parent::extract($linkDefinition);
                continue;
            }

            if (!is_array($linkDefinition)) {
                throw new DomainException(sprintf(
                    'Link object for relation "%s" in resource was malformed; cannot generate link',
                    $rel
                ));
            }

            $aggregate = array();
            foreach ($linkDefinition as $subLink) {
                if (!$subLink instanceof Link) {
                    throw new DomainException(sprintf(
                        'Link object aggregated for relation "%s" in resource was malformed; cannot generate link',
                        $rel
                    ));
                }

                $aggregate[] = parent::extract($subLink);
            }
            
            $links[$rel] = $aggregate;
        }

        return $links;
    }
}
