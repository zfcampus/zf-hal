<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Link;

use ZF\Hal\Collection;

interface PaginationInjectorInterface
{
    /**
     * Generate HAL links for a paginated collection
     *
     * @param  Collection $halCollection
     * @return boolean|ApiProblem
     */
    public function injectPaginationLinks(Collection $halCollection);
}
