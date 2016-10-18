<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Extractor;

use ZF\ApiProblem\Exception\DomainException;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkUrlBuilder;

class LinkExtractor implements LinkExtractorInterface
{
    /**
     * @var LinkUrlBuilder
     */
    protected $linkUrlBuilder;

    /**
     * @param  LinkUrlBuilder $linkUrlBuilder
     */
    public function __construct(LinkUrlBuilder $linkUrlBuilder)
    {
        $this->linkUrlBuilder = $linkUrlBuilder;
    }

    /**
     * @inheritDoc
     */
    public function extract(Link $object)
    {
        if (! $object->isComplete()) {
            throw new DomainException(sprintf(
                'Link from resource provided to %s was incomplete; must contain a URL or a route',
                __METHOD__
            ));
        }

        $representation = $object->getProps();

        if ($object->hasUrl()) {
            $representation['href'] = $object->getUrl();

            return $representation;
        }

        $reuseMatchedParams = true;
        $options = $object->getRouteOptions();
        if (isset($options['reuse_matched_params'])) {
            $reuseMatchedParams = (bool) $options['reuse_matched_params'];
            unset($options['reuse_matched_params']);
        }

        $representation['href'] = $this->linkUrlBuilder->buildLinkUrl(
            $object->getRoute(),
            $object->getRouteParams(),
            $options,
            $reuseMatchedParams
        );

        return $representation;
    }
}
