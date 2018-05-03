<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Link;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Psr\Link\LinkInterface;
use ZF\ApiProblem\Exception;

/**
 * Object describing a collection of link relations
 */
class LinkCollection implements Countable, IteratorAggregate
{
    /**
     * @var array
     */
    protected $links = [];

    /**
     * Return a count of link relations
     *
     * @return int
     */
    public function count()
    {
        return count($this->links);
    }

    /**
     * Retrieve internal iterator
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->links);
    }

    /**
     * Add a link
     *
     * @deprecated Since 1.5.0; use idempotentAdd() for PSR-13 and RFC 5988 compliance.
     * @param  Link $link
     * @param  bool $overwrite
     * @return self
     * @throws Exception\DomainException
     */
    public function add(Link $link, $overwrite = false)
    {
        $relation = $link->getRelation();
        if (! isset($this->links[$relation]) || $overwrite || 'self' == $relation) {
            $this->links[$relation] = $link;
            return $this;
        }

        if ($this->links[$relation] instanceof LinkInterface) {
            $this->links[$relation] = [$this->links[$relation]];
        }

        if (! is_array($this->links[$relation])) {
            $type = (is_object($this->links[$relation])
                ? get_class($this->links[$relation])
                : gettype($this->links[$relation]));

            throw new Exception\DomainException(sprintf(
                '%s::$links should be either a %s\Link or an array; however, it is a "%s"',
                __CLASS__,
                __NAMESPACE__,
                $type
            ));
        }

        $this->links[$relation][] = $link;
        return $this;
    }

    /**
     * Add a link to the collection and update the collection's relations according to RFC 5988.
     *
     * @todo Rename to "add" after deprecating the current "add" implementation
     * @param LinkInterface $link
     * @return void
     */
    public function idempotentAdd(LinkInterface $link)
    {
        $existingRels = array_keys($this->links);
        $linkRels = $link->getRels();

        // update existing rels
        $intersection = array_intersect($linkRels, $existingRels);
        foreach ($intersection as $relation) {
            $relationLinks = $this->links[$relation];
            if (! is_array($relationLinks)) {
                $relationLinks = [$relationLinks];
            }

            if (! in_array($link, $relationLinks, true)) {
                $relationLinks[] = $link;
                $this->links[$relation] = $relationLinks; // inside the if, otherwise it's not really idempotent
            }
        }

        // add missing rels
        $diff = array_diff($linkRels, $existingRels);
        foreach ($diff as $relation) {
            $this->links[$relation] = $link;
        }
    }

    /**
     * Retrieve a link relation
     *
     * @param  string $relation
     * @return LinkInterface|Link|array|null
     */
    public function get($relation)
    {
        if (! $this->has($relation)) {
            return null;
        }
        return $this->links[$relation];
    }

    /**
     * Does a given link relation exist?
     *
     * @param  string $relation
     * @return bool
     */
    public function has($relation)
    {
        return array_key_exists($relation, $this->links);
    }

    /**
     * Remove a given link relation
     *
     * @param  string $relation
     * @return bool
     */
    public function remove($relation)
    {
        if (! $this->has($relation)) {
            return false;
        }
        unset($this->links[$relation]);
        return true;
    }
}
