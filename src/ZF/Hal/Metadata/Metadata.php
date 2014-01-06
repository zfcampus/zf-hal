<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2013 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Metadata;

use Zend\Stdlib\Hydrator\HydratorInterface;
use Zend\Stdlib\Hydrator\HydratorPluginManager;
use ZF\Hal\Exception;
use Zend\Filter\FilterChain;

class Metadata
{
    /**
     * Class this metadata applies to
     *
     * @var string
     */
    protected $class;

    /**
     * Name of the field representing the collection
     *
     * @var string
     */
    protected $collectionName = 'items';

    /**
     * Hydrator to use when extracting object of this class
     *
     * @var HydratorInterface
     */
    protected $hydrator;

    /**
     * @var HydratorPluginManager
     */
    protected $hydrators;

    /**
     * Name of the field representing the identifier
     *
     * @var string
     */
    protected $entityIdentifierName;

    /**
     * Name of the route parameter identifier for the resource
     *
     * @var string
     */
    protected $routeIdentifierName;

    /**
     * Does the class represent a collection?
     *
     * @var bool
     */
    protected $isCollection = false;

    /**
     * Collection of additional relational links to inject in resource
     *
     * @var array
     */
    protected $links = array();

    /**
     * Route for resources composed in a collection
     *
     * @var string
     */
    protected $resourceRoute;

    /**
     * Route to use to generate a self link for this resource
     *
     * @var string
     */
    protected $route;

    /**
     * Additional options to use when generating a self link for this resource
     *
     * @var array
     */
    protected $routeOptions = array();

    /**
     * Additional route parameters to use when generating a self link for this resource
     *
     * @var array
     */
    protected $routeParams = array();

    /**
     * URL to use for this resource (instead of a route)
     *
     * @var string
     */
    protected $url;

    /**
     * Constructor
     *
     * Sets the class, and passes any options provided to the appropriate
     * setter methods, after first converting them to lowercase and stripping
     * underscores.
     *
     * If the class does not exist, raises an exception.
     *
     * @param  string $class
     * @param  array $options
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($class, array $options = array(), HydratorPluginManager $hydrators = null)
    {
        $filter = new FilterChain();
        $filter->attachByName('WordUnderscoreToCamelCase')
               ->attachByName('StringToLower');

        if (!class_exists($class)) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Class provided to %s must exist; received "%s"',
                __CLASS__,
                $class
            ));
        }
        $this->class = $class;

        if (null !== $hydrators) {
            $this->hydrators = $hydrators;
        }

        foreach ($options as $key => $value) {
            $filteredKey = $filter($key);

            if ($filteredKey === 'class') {
                continue;
            }

            // Strip "name" from route_name and resource_route_name keys (and
            // continue honoring simply "route" and "resource_route")
            // Don't generically strip all 'name's
            if ($filteredKey === 'routename') {
                $filteredKey = 'route';
            }
            if ($filteredKey === 'resourceroutename') {
                $filteredKey = 'resourceroute';
            }

            $method = 'set' . $filteredKey;
            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                throw new Exception\InvalidArgumentException(
                    "Unhandled option passed to Metadata constructor: $method " . $key);
            }
        }
    }

    /**
     * Retrieve the class this metadata is associated with
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Retrieve the collection name
     *
     * @return string
     */
    public function getCollectionName()
    {
        return $this->collectionName;
    }

    /**
     * Retrieve the hydrator to associate with this class, if any
     *
     * @return null|HydratorInterface
     */
    public function getHydrator()
    {
        return $this->hydrator;
    }

    /**
     * Retrieve the entity identifier name
     *
     * @return string
     */
    public function getEntityIdentifierName()
    {
        return $this->entityIdentifierName;
    }

    /**
     * Retrieve the route identifier name
     *
     * @return string
     */
    public function getRouteIdentifierName()
    {
        return $this->routeIdentifierName;
    }

    /**
     * Retrieve set of relational links to inject, if any
     *
     * @return array
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * Retrieve the resource route
     *
     * If not set, uses the route or url, depending on which is present.
     *
     * @return null|string
     */
    public function getResourceRoute()
    {
        if (null === $this->resourceRoute) {
            if ($this->hasRoute()) {
                $this->setResourceRoute($this->getRoute());
            } else {
                $this->setResourceRoute($this->getUrl());
            }
        }
        return $this->resourceRoute;
    }

    /**
     * Retrieve the route to use for URL generation
     *
     * @return null|string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Retrieve an route options to use in URL generation
     *
     * @return array
     */
    public function getRouteOptions()
    {
        return $this->routeOptions;
    }

    /**
     * Retrieve any route parameters to use in URL generation
     *
     * @return array
     */
    public function getRouteParams()
    {
        return $this->routeParams;
    }

    /**
     * Retrieve the URL to use for this resource, if present
     *
     * @return null|string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Is a hydrator associated with this class?
     *
     * @return bool
     */
    public function hasHydrator()
    {
        return (null !== $this->hydrator);
    }

    /**
     * Is a route present for this class?
     *
     * @return bool
     */
    public function hasRoute()
    {
        return (null !== $this->route);
    }

    /**
     * Is a URL set for this class?
     *
     * @return bool
     */
    public function hasUrl()
    {
        return (null !== $this->url);
    }

    /**
     * Does this class represent a collection?
     *
     * @return bool
     */
    public function isCollection()
    {
        return $this->isCollection;
    }

    /**
     * Set the collection name
     *
     * @param  string $collectionName
     * @return self
     */
    public function setCollectionName($collectionName)
    {
        $this->collectionName = (string) $collectionName;
        return $this;
    }

    /**
     * Set the hydrator to use with this class
     *
     * @param  string|HydratorInterface $hydrator
     * @return self
     * @throws Exception\InvalidArgumentException if the class or hydrator does not implement HydratorInterface
     */
    public function setHydrator($hydrator)
    {
        if (is_string($hydrator)) {
            if (null !== $this->hydrators
                && $this->hydrators->has($hydrator)
            ) {
                $hydrator = $this->hydrators->get($hydrator);
            } elseif (class_exists($hydrator)) {
                $hydrator = new $hydrator();
            }
        }
        if (!$hydrator instanceof HydratorInterface) {
            if (is_object($hydrator)) {
                $type = get_class($hydrator);
            } elseif (is_string($hydrator)) {
                $type = $hydrator;
            } else {
                $type = gettype($hydrator);
            }
            throw new Exception\InvalidArgumentException(sprintf(
                'Hydrator class must implement Zend\Stdlib\Hydrator\Hydrator; received "%s"',
                $type
            ));
        }
        $this->hydrator = $hydrator;
        return $this;
    }

    /**
     * Set the entity identifier name
     *
     * @param  string|mixed $identifier
     * @return self
     */
    public function setEntityIdentifierName($identifier)
    {
        $this->entityIdentifierName = $identifier;
        return $this;
    }

    /**
     * Set the route identifier name
     *
     * @param  string|mixed $identifier
     * @return self
     */
    public function setRouteIdentifierName($identifier)
    {
        $this->routeIdentifierName = $identifier;
        return $this;
    }

    /**
     * Set the flag indicating collection status
     *
     * @param  bool $flag
     * @return self
     */
    public function setIsCollection($flag)
    {
        $this->isCollection = (bool) $flag;
        return $this;
    }

    /**
     * Set relational links.
     *
     * Each element in the array should be an array with the elements:
     *
     * - rel - the link relation
     * - url - the URL to use for the link OR
     * - route - an array of route information for generating the link; this
     *   should include the elements "name" (required; the route name),
     *   "params" (optional; additional parameters to inject), and "options"
     *   (optional; additional options to pass to the router for assembly)
     *
     * @param  array $links
     * @return self
     */
    public function setLinks(array $links)
    {
        $this->links = $links;
        return $this;
    }

    /**
     * Set the resource route (for embedded resources in collections)
     *
     * @param  string $route
     * @return self
     */
    public function setResourceRoute($route)
    {
        $this->resourceRoute = $route;
        return $this;
    }

    /**
     * Set the route for URL generation
     *
     * @param  string $route
     * @return self
     */
    public function setRoute($route)
    {
        $this->route = $route;
        return $this;
    }

    /**
     * Set route options for URL generation
     *
     * @param  array $options
     * @return self
     */
    public function setRouteOptions(array $options)
    {
        $this->routeOptions = $options;
        return $this;
    }

    /**
     * Set route parameters for URL generation
     *
     * @param  array $params
     * @return self
     */
    public function setRouteParams(array $params)
    {
        $this->routeParams = $params;
        return $this;
    }

    /**
     * Set the URL to use with this resource
     *
     * @param  string $url
     * @return self
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }
}
