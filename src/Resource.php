<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal;

/**
 * @deprecated
 */
class Resource extends Entity
{
    /**
     * @param  object|array $resource
     * @param  mixed $id
     * @throws Exception\InvalidResourceException if resource is not an object or array
     */
    public function __construct($resource, $id)
    {
        trigger_error(sprintf(
            '%s is deprecated; please use %s\Entity instead',
            __CLASS__,
            __NAMESPACE__
        ), E_USER_DEPRECATED);
        parent::__construct($resource, $id);
    }
}
