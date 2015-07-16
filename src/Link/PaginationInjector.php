<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Link;

use Zend\Paginator\Paginator;
use Zend\Stdlib\ArrayUtils;
use ZF\ApiProblem\ApiProblem;
use ZF\Hal\Collection;

class PaginationInjector implements PaginationInjectorInterface
{
    /**
     * @inheritDoc
     */
    public function injectPaginationLinks(Collection $halCollection)
    {
        $collection = $halCollection->getCollection();
        if (! $collection instanceof Paginator) {
            return false;
        }

        $this->configureCollection($halCollection);

        $pageCount = count($collection);
        if ($pageCount === 0) {
            return true;
        }

        $page = $halCollection->getPage();

        if ($page < 1 || $page > $pageCount) {
            return new ApiProblem(409, 'Invalid page provided');
        }

        $this->injectLinks($halCollection);

        return true;
    }

    private function configureCollection(Collection $halCollection)
    {
        $collection = $halCollection->getCollection();
        $page       = $halCollection->getPage();
        $pageSize   = $halCollection->getPageSize();

        $collection->setItemCountPerPage($pageSize);
        $collection->setCurrentPageNumber($page);
    }

    private function injectLinks(Collection $halCollection)
    {
        $this->injectSelfLink($halCollection);
        $this->injectFirstLink($halCollection);
        $this->injectLastLink($halCollection);
        $this->injectPrevLink($halCollection);
        $this->injectNextLink($halCollection);
    }

    private function injectSelfLink(Collection $halCollection)
    {
        $page = $halCollection->getPage();
        $link = $this->createPaginationLink('self', $halCollection, $page);
        $halCollection->getLinks()->add($link, true);
    }

    private function injectFirstLink(Collection $halCollection)
    {
        $link = $this->createPaginationLink('first', $halCollection);
        $halCollection->getLinks()->add($link);
    }

    private function injectLastLink(Collection $halCollection)
    {
        $page = $halCollection->getCollection()->count();
        $link = $this->createPaginationLink('last', $halCollection, $page);
        $halCollection->getLinks()->add($link);
    }

    private function injectPrevLink(Collection $halCollection)
    {
        $page = $halCollection->getPage();
        $prev = ($page > 1) ? $page - 1 : false;

        if ($prev) {
            $link = $this->createPaginationLink('prev', $halCollection, $prev);
            $halCollection->getLinks()->add($link);
        }
    }

    private function injectNextLink(Collection $halCollection)
    {
        $page      = $halCollection->getPage();
        $pageCount = $halCollection->getCollection()->count();
        $next      = ($page < $pageCount) ? $page + 1 : false;

        if ($next) {
            $link = $this->createPaginationLink('next', $halCollection, $next);
            $halCollection->getLinks()->add($link);
        }
    }

    private function createPaginationLink($relation, Collection $halCollection, $page = null)
    {
        $options = ArrayUtils::merge(
            $halCollection->getCollectionRouteOptions(),
            ['query' => ['page' => $page]]
        );

        return Link::factory([
            'rel'   => $relation,
            'route' => [
                'name'    => $halCollection->getCollectionRoute(),
                'params'  => $halCollection->getCollectionRouteParams(),
                'options' => $options,
            ],
        ]);
    }
}
