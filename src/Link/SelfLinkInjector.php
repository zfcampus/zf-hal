<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Link;

use ZF\Hal\Link\LinkCollectionAwareInterface;
use ZF\Hal\Collection;
use ZF\Hal\Entity;

class SelfLinkInjector implements SelfLinkInjectorInterface
{
    /**
     * @inheritDoc
     */
    public function injectSelfLink(LinkCollectionAwareInterface $resource, $route, $routeIdentifier = 'id')
    {
        $links = $resource->getLinks();
        if ($links->has('self')) {
            return;
        }

        $selfLink = $this->createSelfLink($resource, $route, $routeIdentifier);

        $links->add($selfLink, true);
    }

    private function createSelfLink($resource, $route, $routeIdentifier)
    {
        $link = new Link('self');
        $link->setRoute($route);

        $routeParams = $this->getRouteParams($resource, $routeIdentifier);
        if (!empty($routeParams)) {
            $link->setRouteParams($routeParams);
        }

        $routeOptions = $this->getRouteOptions($resource);
        if (!empty($routeOptions)) {
            $link->setRouteOptions($routeOptions);
        }

        return $link;
    }

    private function getRouteParams($resource, $routeIdentifier)
    {
        if ($resource instanceof Collection) {
            return $resource->getCollectionRouteParams();
        }

        $routeParams = [];

        if ($resource instanceof Entity
            && null !== $resource->getId()
        ) {
            $routeParams = [
                $routeIdentifier => $resource->getId(),
            ];
        }

        return $routeParams;
    }

    private function getRouteOptions($resource)
    {
        if ($resource instanceof Collection) {
            return $resource->getCollectionRouteOptions();
        }

        return [];
    }
}
