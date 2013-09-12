<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZF\Hal\Parser;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use OutOfRangeException;
use Zend\Stdlib\ArrayUtils;
use ZF\Hal\Exception;
use ZF\Hal\Link;

/**
 * HAL resource
 * 
 * Allows access to individual properties, including embedded resources, as well as relational 
 * links.
 *
 * You may also grab all properties at once (via getData())
 *
 * Inspired by https://github.com/blongden/hal
 */
class Resource implements 
    IteratorAggregate,
    JsonSerializable,
    Link\LinkCollectionAwareInterface
{
    /**
     * Embedded resources
     * 
     * @var array
     */
    protected $embedded = array();

    /**
     * @var Link\LinkCollection
     */
    protected $links;

    /**
     * Resource data (minus embedded resources)
     * 
     * @var array
     */
    protected $resource;

    /**
     * @param  array $resource 
     * @param  Link\LinkCollection $links 
     * @param  array $embedded 
     */
    public function __construct(array $resource = array(), Link\LinkCollection $links = null, array $embedded = null)
    {
        if (null === $links) {
            $links = new Link\LinkCollection();
        }
        $this->resource = $resource;
        $this->setLinks($links);

        if (null !== $embedded) {
            $this->embedded = $embedded;
        }
    }

    /**
     * Access individual properties of the resource, including 
     * embedded resources, by name
     * 
     * @param  string $name 
     * @return mixed
     * @throws OutOfRangeException for unknown properties
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->resource)) {
            return $this->resource[$name];
        }

        if (array_key_exists($name, $this->embedded)) {
            return $this->embedded[$name];
        }

        throw new OutOfRangeException(sprintf(
            'The value "%s" does not exist in the resource',
            $name
        ));
    }

    /**
     * Serialize to JSON string
     * 
     * @return string
     */
    public function __toString()
    {
        return json_encode($this, JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK|JSON_UNESCAPED_SLASHES);
    }

    /**
     * Retrieve iterator
     *
     * Iterates over all properties and embedded resources
     * 
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator(array_merge($this->resource, $this->embedded));
    }

    /**
     * Return data structure for JSON serialization
     * 
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Set link collection
     *
     * Link collection may not be set after instantiation.
     * 
     * @param  Link\LinkCollection $links 
     * @throws Exception\RuntimeException for attempts to set link collection after instantiation
     */
    public function setLinks(Link\LinkCollection $links)
    {
        if ($this->links instanceof Link\LinkCollection) {
            throw new Exception\RuntimeException(
                'Link collection may not be set after instantiation'
            );
        }
        $this->links = $links;
    }

    /**
     * Retrieve link collection
     * 
     * @return Link\LinkCollection
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * Return resource as array, minus any links
     *
     * Embedded resources are returned as normal properties
     *
     * @return array
     */
    public function getData()
    {
        return $this->resource;
    }

    /**
     * Return list of embedded resources
     * 
     * @return array
     */
    public function getResources()
    {
        return $this->embedded;
    }

    /**
     * Return resource as array
     *
     * Returns HAL structure as an associative array
     * 
     * @return array
     */
    public function toArray()
    {
        $links    = $this->extractLinks();
        $embedded = $this->embedded;
        $data     = $this->resource;

        $halProperties = array();
        if (!empty($links)) {
            $halProperties['_links'] = $links;
        }
        if (!empty($embedded)) {
            $halProperties['_embedded'] = $embedded;
        }

        return array_merge($data, $halProperties);
    }

    /**
     * Extract links into a HAL structure
     * 
     * @return array
     */
    protected function extractLinks()
    {
        $links = array();
        foreach ($this->links as $link) {
            if (!$link->isComplete()) {
                // @todo raise a warning for incomplete links
                continue;
            }
            if (!$link->hasUrl()) {
                // @todo should likely allow rendering 
                // route-based links as well; for now, raise a 
                // warning?
                continue;
            }
            $links[$link->getRelation()] = array('href' => array(
                $link->getUrl(),
            ));
        }
        return $links;
    }
}
