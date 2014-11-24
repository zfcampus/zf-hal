<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Link;

use Traversable;
use Zend\Stdlib\ArrayUtils;
use Zend\Uri\Exception as UriException;
use Zend\Uri\UriFactory;
use ZF\ApiProblem\Exception\DomainException;
use ZF\Hal\Exception;

/**
 * Object describing a link relation
 */
class Link
{
    /**
     * @var array
     */
    protected $props = array();

    /**
     * @var string
     */
    protected $relation;

    /**
     * @var string
     */
    protected $route;

    /**
     * @var array
     */
    protected $routeOptions = array();

    /**
     * @var array
     */
    protected $routeParams = array();

    /**
     * @var string
     */
    protected $url;

    /**
     * Create a link relation
     *
     * @todo  filtering and/or validation of relation string
     * @param string $relation
     */
    public function __construct($relation)
    {
        $this->relation = (string) $relation;
    }

    /**
     * Factory for creating links
     *
     * @param  array $spec
     * @return self
     * @throws Exception\InvalidArgumentException if missing a "rel" or invalid route specifications
     */
    public static function factory(array $spec)
    {
        if (!isset($spec['rel'])) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s requires that the specification array contain a "rel" element; none found',
                __METHOD__
            ));
        }
        $link = new static($spec['rel']);

        if (isset($spec['props'])
            && is_array($spec['props'])
        ) {
            $link->setProps($spec['props']);
        }

        if (isset($spec['url'])) {
            $link->setUrl($spec['url']);
            return $link;
        }

        if (isset($spec['route'])) {
            $routeInfo = $spec['route'];
            if (is_string($routeInfo)) {
                $link->setRoute($routeInfo);
                return $link;
            }

            if (!is_array($routeInfo)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    '%s requires that the specification array\'s "route" element be a string or array; received "%s"',
                    __METHOD__,
                    (is_object($routeInfo) ? get_class($routeInfo) : gettype($routeInfo))
                ));
            }

            if (!isset($routeInfo['name'])) {
                throw new Exception\InvalidArgumentException(sprintf(
                    '%s requires that the specification array\'s "route" array contain a "name" element; none found',
                    __METHOD__
                ));
            }
            $name    = $routeInfo['name'];
            $params  = isset($routeInfo['params']) && is_array($routeInfo['params'])
                ? $routeInfo['params']
                : array();
            $options = isset($routeInfo['options']) && is_array($routeInfo['options'])
                ? $routeInfo['options']
                : array();
            $link->setRoute($name, $params, $options);
            return $link;
        }

        return $link;
    }

    /**
     * Set any additional, arbitrary properties to include in the link object
     *
     * "href" will be ignored.
     *
     * @param  array $props
     * @return self
     */
    public function setProps(array $props)
    {
        if (isset($props['href'])) {
            unset($props['href']);
        }
        $this->props = $props;
        return $this;
    }

    /**
     * Set the route to use when generating the relation URI
     *
     * If any params or options are passed, those will be passed to route assembly.
     *
     * @param  string $route
     * @param  null|array|Traversable $params
     * @param  null|array|Traversable $options
     * @return self
     * @throws DomainException
     */
    public function setRoute($route, $params = null, $options = null)
    {
        if ($this->hasUrl()) {
            throw new DomainException(sprintf(
                '%s already has a URL set; cannot set route',
                __CLASS__
            ));
        }

        $this->route = (string) $route;
        if ($params) {
            $this->setRouteParams($params);
        }
        if ($options) {
            $this->setRouteOptions($options);
        }
        return $this;
    }

    /**
     * Set route assembly options
     *
     * @param  array|Traversable $options
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setRouteOptions($options)
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }

        if (!is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($options) ? get_class($options) : gettype($options))
            ));
        }

        $this->routeOptions = $options;
        return $this;
    }

    /**
     * Set route assembly parameters/substitutions
     *
     * @param  array|Traversable $params
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function setRouteParams($params)
    {
        if ($params instanceof Traversable) {
            $params = ArrayUtils::iteratorToArray($params);
        }

        if (!is_array($params)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable; received "%s"',
                __METHOD__,
                (is_object($params) ? get_class($params) : gettype($params))
            ));
        }

        $this->routeParams = $params;
        return $this;
    }

    /**
     * Set an explicit URL for the link relation
     *
     * @param  string $url
     * @return self
     * @throws DomainException
     * @throws Exception\InvalidArgumentException
     */
    public function setUrl($url)
    {
        if ($this->hasRoute()) {
            throw new DomainException(sprintf(
                '%s already has a route set; cannot set URL',
                __CLASS__
            ));
        }

        try {
            $uri = UriFactory::factory($url);
        } catch (UriException\ExceptionInterface $e) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Received invalid URL: %s',
                $e->getMessage()
            ), $e->getCode(), $e);
        }

        if (!$uri->isValid()) {
            throw new Exception\InvalidArgumentException(
                'Received invalid URL'
            );
        }

        $this->url = $url;
        return $this;
    }

    /**
     * Get additional properties to include in Link representation
     *
     * @return array
     */
    public function getProps()
    {
        return $this->props;
    }

    /**
     * Retrieve the link relation
     *
     * @return string
     */
    public function getRelation()
    {
        return $this->relation;
    }

    /**
     * Return the route to be used to generate the link URL, if any
     *
     * @return null|string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * Retrieve route assembly options, if any
     *
     * @return array
     */
    public function getRouteOptions()
    {
        return $this->routeOptions;
    }

    /**
     * Retrieve route assembly parameters/substitutions, if any
     *
     * @return array
     */
    public function getRouteParams()
    {
        return $this->routeParams;
    }

    /**
     * Retrieve the link URL, if set
     *
     * @return null|string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Is the link relation complete -- do we have either a URL or a route set?
     *
     * @return bool
     */
    public function isComplete()
    {
        return (!empty($this->url) || !empty($this->route));
    }

    /**
     * Does the link have a route set?
     *
     * @return bool
     */
    public function hasRoute()
    {
        return !empty($this->route);
    }

    /**
     * Does the link have a URL set?
     *
     * @return bool
     */
    public function hasUrl()
    {
        return !empty($this->url);
    }
}
