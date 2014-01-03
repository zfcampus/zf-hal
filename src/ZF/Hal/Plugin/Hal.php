<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2013 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Plugin;

use ArrayObject;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\Plugin\PluginInterface as ControllerPluginInterface;
use Zend\Paginator\Paginator;
use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\DispatchableInterface;
use Zend\Stdlib\Hydrator\HydratorInterface;
use Zend\Stdlib\Hydrator\HydratorPluginManager;
use Zend\View\Helper\AbstractHelper;
use Zend\View\Helper\ServerUrl;
use Zend\View\Helper\Url;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\Exception\DomainException;
use ZF\Hal\Exception;
use ZF\Hal\Collection;
use ZF\Hal\Resource;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkCollection;
use ZF\Hal\Link\LinkCollectionAwareInterface;
use ZF\Hal\Metadata\Metadata;
use ZF\Hal\Metadata\MetadataMap;

/**
 * Generate links for use with HAL payloads
 */
class Hal extends AbstractHelper implements
    ControllerPluginInterface,
    EventManagerAwareInterface
{
    /**
     * @var DispatchableInterface
     */
    protected $controller;

    /**
     * Default hydrator to use if no hydrator found for a specific resource class.
     *
     * @var HydratorInterface
     */
    protected $defaultHydrator;

    /**
     * Boolean to render embedded resources or just include _embedded data
     *
     * @var boolean
     */
    protected $renderEmbeddedResources = true;

    /**
     * Boolean to render collections or just return their _embedded data
     *
     * @var boolean
     */
    protected $renderCollections = true;

    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * Map of class name/(hydrator instance|name) pairs
     *
     * @var array
     */
    protected $hydratorMap = array();

    /**
     * @var HydratorPluginManager
     */
    protected $hydrators;

    /**
     * @var MetadataMap
     */
    protected $metadataMap;

    /**
     * @var ServerUrl
     */
    protected $serverUrlHelper;

    /**
     * @var Url
     */
    protected $urlHelper;

    /**
     * @param null|HydratorPluginManager $hydrators
     */
    public function __construct(HydratorPluginManager $hydrators = null)
    {
        if (null === $hydrators) {
            $hydrators = new HydratorPluginManager();
        }
        $this->hydrators = $hydrators;
    }

    /**
     * @param DispatchableInterface $controller
     */
    public function setController(DispatchableInterface $controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return DispatchableInterface
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * Retrieve the event manager instance
     *
     * Lazy-initializes one if none present.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (!$this->events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }

    /**
     * Set the event manager instance
     *
     * @param  EventManagerInterface $events
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            __CLASS__,
            get_class($this),
        ));
        $this->events = $events;

        $events->attach('getIdFromResource', function ($e) {
            $resource = $e->getParam('resource');

            // Found id in array
            if (is_array($resource) && array_key_exists('id', $resource)) {
                return $resource['id'];
            }

            // No id in array, or not an object; return false
            if (is_array($resource) || !is_object($resource)) {
                return false;
            }

            // Found public id property on object
            if (isset($resource->id)) {
                return $resource->id;
            }

            // Found public id getter on object
            if (method_exists($resource, 'getid')) {
                return $resource->getId();
            }

            // not found
            return false;
        });

        return $this;
    }

    /**
     * @return HydratorPluginManager
     */
    public function getHydratorManager()
    {
        return $this->hydrators;
    }

    /**
     * Retrieve the metadata map
     *
     * @return MetadataMap
     */
    public function getMetadataMap()
    {
        if (!$this->metadataMap instanceof MetadataMap) {
            $this->setMetadataMap(new MetadataMap());
        }
        return $this->metadataMap;
    }

    /**
     * Set the metadata map
     *
     * @param  MetadataMap $map
     * @return self
     */
    public function setMetadataMap(MetadataMap $map)
    {
        $this->metadataMap = $map;
        return $this;
    }

    /**
     * @param ServerUrl $helper
     */
    public function setServerUrlHelper(ServerUrl $helper)
    {
        $this->serverUrlHelper = $helper;
    }

    /**
     * @param Url $helper
     */
    public function setUrlHelper(Url $helper)
    {
        $this->urlHelper = $helper;
    }

    /**
     * Map a resource class to a specific hydrator instance
     *
     * @param  string $class
     * @param  HydratorInterface $hydrator
     * @return RestfulJsonRenderer
     */
    public function addHydrator($class, $hydrator)
    {
        if (!$hydrator instanceof HydratorInterface) {
            if (!$this->hydrators->has((string) $hydrator)) {
                throw new Exception\InvalidArgumentException(sprintf(
                    'Invalid hydrator instance or name provided; received "%s"',
                    (is_object($hydrator) ? get_class($hydrator) : (is_string($hydrator) ? $hydrator : gettype($hydrator)))
                ));
            }
            $hydrator = $this->hydrators->get($hydrator);
        }
        $class = strtolower($class);
        $this->hydratorMap[$class] = $hydrator;
        return $this;
    }

    /**
     * Set the default hydrator to use if none specified for a class.
     *
     * @param  HydratorInterface $hydrator
     * @return RestfulJsonRenderer
     */
    public function setDefaultHydrator(HydratorInterface $hydrator)
    {
        $this->defaultHydrator = $hydrator;
        return $this;
    }

    /**
     * Set boolean to render embedded resources or just include _embedded data
     *
     * @var boolean
     */
    public function setRenderEmbeddedResources($value)
    {
        $this->renderEmbeddedResources = $value;
        return $this;
    }

    /**
     * Get boolean to render embedded resources or just include _embedded data
     *
     * @return boolean
     */
    public function getRenderEmbeddedResources()
    {
        return $this->renderEmbeddedResources;
    }

    /**
     * Set boolean to render embedded resources or just include _embedded data
     *
     * @var boolean
     */
    public function setRenderCollections($value)
    {
        $this->renderCollections = $value;
        return $this;
    }

    /**
     * Get boolean to render embedded resources or just include _embedded data
     *
     * @return boolean
     */
    public function getRenderCollections()
    {
        return $this->renderCollections;
    }

    /**
     * Retrieve a hydrator for a given resource
     *
     * If the resource has a mapped hydrator, returns that hydrator. If not, and
     * a default hydrator is present, the default hydrator is returned.
     * Otherwise, a boolean false is returned.
     *
     * @param  object $resource
     * @return HydratorInterface|false
     */
    public function getHydratorForResource($resource)
    {
        $metadataMap = $this->getMetadataMap();
        if ($metadataMap->has($resource)) {
            $metadata = $metadataMap->get($resource);
            $hydrator = $metadata->getHydrator();
            if ($hydrator instanceof HydratorInterface) {
                return $hydrator;
            }
        }

        $class = strtolower(get_class($resource));
        if (isset($this->hydratorMap[$class])) {
            return $this->hydratorMap[$class];
        }

        if ($this->defaultHydrator instanceof HydratorInterface) {
            return $this->defaultHydrator;
        }

        return false;
    }

    /**
     * "Render" a Collection
     *
     * Injects pagination links, if the composed collection is a Paginator, and
     * then loops through the collection to create the data structure representing
     * the collection.
     *
     * For each resource in the collection, the event "renderCollection.resource" is
     * triggered, with the following parameters:
     *
     * - "collection", which is the $halCollection passed to the method
     * - "resource", which is the current resource
     * - "route", the resource route that will be used to generate links
     * - "routeParams", any default routing parameters/substitutions to use in URL assembly
     * - "routeOptions", any default routing options to use in URL assembly
     *
     * This event can be useful particularly when you have multi-segment routes
     * and wish to ensure that route parameters are injected, or if you want to
     * inject query or fragment parameters.
     *
     * Event parameters are aggregated in an ArrayObject, which allows you to
     * directly manipulate them in your listeners:
     *
     * <code>
     * $params = $e->getParams();
     * $params['routeOptions']['query'] = array('format' => 'json');
     * </code>
     *
     * @param  Collection $halCollection
     * @return array|ApiProblem Associative array representing the payload to render; returns ApiProblem if error in pagination occurs
     */
    public function renderCollection(Collection $halCollection)
    {
        $this->getEventManager()->trigger(__FUNCTION__, $this, array('collection' => $halCollection));
        $collection     = $halCollection->getCollection();
        $collectionName = $halCollection->getCollectionName();

        if ($collection instanceof Paginator) {
            $status = $this->injectPaginationLinks($halCollection);
            if ($status instanceof ApiProblem) {
                return $status;
            }
        }

        $payload = $halCollection->getAttributes();
        $payload['_links']    = $this->fromResource($halCollection);
        $payload['_embedded'] = array(
            $collectionName => $this->extractCollection($halCollection),
        );

        return $payload;
    }

    /**
     * Render an individual resource
     *
     * Creates a hash representation of the Resource. The resource is first
     * converted to an array, and its associated links are injected as the
     * "_links" member. If any members of the resource are themselves
     * Resource objects, they are extracted into an "_embedded" hash.
     *
     * @param  Resource $halResource
     * @return array
     */
    public function renderResource(Resource $halResource, $renderResource = true)
    {
        $this->getEventManager()->trigger(__FUNCTION__, $this, array('resource' => $halResource));
        $resource      = $halResource->resource;
        $id            = $halResource->id;
        $resourceLinks = $halResource->getLinks();
        $metadataMap   = $this->getMetadataMap();

        if (!is_array($resource)) {
            $resource = $this->convertResourceToArray($resource);
        }

        if (!$renderResource) $resource = array();

        foreach ($resource as $key => $value) {
            if (is_object($value) && $metadataMap->has($value)) {
                $value = $this->createResourceFromMetadata($value, $metadataMap->get($value), $this->getRenderEmbeddedResources());
            }

            if ($value instanceof Resource) {
                $this->extractEmbeddedResource($resource, $key, $value);
            }
            if ($value instanceof Collection) {
                $this->extractEmbeddedCollection($resource, $key, $value);
            }
            if ($value instanceof Link) {
                $resourceLinks->add($value);
                unset($resource[$key]);
            }
            if ($value instanceof LinkCollection) {
                array_walk_recursive($value, function ($link, $rel) use ($resourceLinks) {
                    $resourceLinks->add($link);
                });
                unset($resource[$key]);
            }
        }

        $resource['_links'] = $this->fromResource($halResource);
        return $resource;
    }

    /**
     * Create a fully qualified URI for a link
     *
     * Triggers the "createLink" event with the route, id, resource, and a set of
     * params that will be passed to the route; listeners can alter any of the
     * arguments, which will then be used by the method to generate the url.
     *
     * @param  string $route
     * @param  null|false|int|string $id
     * @param  null|mixed $resource
     * @return string
     */
    public function createLink($route, $id = null, $resource = null)
    {
        $params             = new ArrayObject();
        $reUseMatchedParams = true;

        if (false === $id) {
            $reUseMatchedParams = false;
        } elseif (null !== $id) {
            $params['id'] = $id;
        }

        $events      = $this->getEventManager();
        $eventParams = $events->prepareArgs(array(
            'route'    => $route,
            'id'       => $id,
            'resource' => $resource,
            'params'   => $params,
        ));
        $events->trigger(__FUNCTION__, $this, $eventParams);
        $route = $eventParams['route'];

        $path = call_user_func($this->urlHelper, $route, $params->getArrayCopy(), $reUseMatchedParams);

        if (substr($path, 0, 4) == 'http') {
            return $path;
        }

        return call_user_func($this->serverUrlHelper, $path);
    }

    /**
     * Create a URL from a Link
     *
     * @param  Link $linkDefinition
     * @return array
     * @throws Exception\DomainException if Link is incomplete
     */
    public function fromLink(Link $linkDefinition)
    {
        if (!$linkDefinition->isComplete()) {
            throw new DomainException(sprintf(
                'Link from resource provided to %s was incomplete; must contain a URL or a route',
                __METHOD__
            ));
        }

        $representation = $linkDefinition->getProps();

        if ($linkDefinition->hasUrl()) {
            return array_merge($representation, array(
                'href' => $linkDefinition->getUrl(),
            ));
        }

        $reuseMatchedParams = true;
        $options = $linkDefinition->getRouteOptions();
        if (isset($options['reuse_matched_params'])) {
            $reuseMatchedParams = (bool) $options['reuse_matched_params'];
            unset($options['reuse_matched_params']);
        }

        $path = call_user_func(
            $this->urlHelper,
            $linkDefinition->getRoute(),
            $linkDefinition->getRouteParams(),
            $options,
            $reuseMatchedParams
        );

        if (substr($path, 0, 4) == 'http') {
            return array_merge($representation, array(
                'href' => $path,
            ));
        }

        return array_merge($representation, array(
            'href' => call_user_func($this->serverUrlHelper, $path),
        ));
    }

    /**
     * Generate HAL links from a LinkCollection
     *
     * @param  LinkCollection $collection
     * @return array
     */
    public function fromLinkCollection(LinkCollection $collection)
    {
        $links = array();
        foreach($collection as $rel => $linkDefinition) {
            if ($linkDefinition instanceof Link) {
                $links[$rel] = $this->fromLink($linkDefinition);
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
                $aggregate[] = $this->fromLink($subLink);
            }
            $links[$rel] = $aggregate;
        }
        return $links;
    }

    /**
     * Create HAL links "object" from a resource/collection
     *
     * @param  LinkCollectionAwareInterface $resource
     * @return array
     */
    public function fromResource(LinkCollectionAwareInterface $resource)
    {
        return $this->fromLinkCollection($resource->getLinks());
    }

    /**
     * Create a resource and/or collection based on a metadata map
     *
     * @param  object $object
     * @param  Metadata $metadata
     * @return Resource|Collection
     */
    public function createResourceFromMetadata($object, Metadata $metadata, $renderEmbeddedResources = true)
    {
        if ($metadata->isCollection()) {
            return $this->createCollectionFromMetadata($object, $metadata);
        }

        if ($metadata->hasHydrator()) {
            $hydrator = $metadata->getHydrator();
        } else {
            $hydrator = $this->getHydratorForResource($object);
        }
        if (!$hydrator instanceof HydratorInterface) {
            throw new Exception\RuntimeException(sprintf(
                'Unable to extract %s; no hydrator registered',
                get_class($object)
            ));
        }
        $data = $hydrator->extract($object);

        $entityIdentiferName = $metadata->getEntityIdentifierName();
        if (!isset($data[$entityIdentiferName])) {
            throw new Exception\RuntimeException(sprintf(
                'Unable to determine entity identifier for object of type "%s"; no fields matching "%s"',
                get_class($object),
                $entityIdentiferName
            ));
        }
        $id = $data[$entityIdentiferName];

        if (!$renderEmbeddedResources) $data = array();

        $resource = new Resource($data, $id);
        $links    = $resource->getLinks();
        $this->marshalMetadataLinks($metadata, $links);
        if (!$links->has('self')) {
            $link = $this->marshalSelfLinkFromMetadata($metadata, $object, $id, $metadata->getRouteIdentifierName());
            $links->add($link);
        }

        return $resource;
    }

    /**
     * Create a Resource instance and inject it with a self relational link
     *
     * @param  Resource|array|object $resource
     * @param  string $route
     * @param  string $routeIdentifierName
     * @return Resource
     */
    public function createResource($resource, $route, $routeIdentifierName)
    {
        $metadataMap = $this->getMetadataMap();
        if (is_object($resource) && $metadataMap->has($resource)) {
            $resource = $this->createResourceFromMetadata($resource, $metadataMap->get($resource));
        }

        if (!$resource instanceof Resource) {
            $id = $this->getIdFromResource($resource);
            if (!$id) {
                return new ApiProblem(
                    422,
                    'No resource identifier present following resource creation.'
                );
            }
            $resource = new Resource($resource, $id);
        }

        $this->injectSelfLink($resource, $route, $routeIdentifierName);
        return $resource;
    }

    /**
     * Creates a Collection instance with a self relational link
     *
     * @param  Collection|array|object $collection
     * @param  null|string $route
     * @param  string $identiferName
     * @return Collection
     */
    public function createCollection($collection, $route = null)
    {
        $metadataMap = $this->getMetadataMap();
        if (is_object($collection) && $metadataMap->has($collection)) {
            $collection = $this->createCollectionFromMetadata($collection, $metadataMap->get($collection));
        }

        if (!$collection instanceof Collection) {
            $collection = new Collection($collection);
        }

        $this->injectSelfLink($collection, $route);
        return $collection;
    }

    /**
     * @param  object $object
     * @param  Metadata $metadata
     * @return Collection
     */
    public function createCollectionFromMetadata($object, Metadata $metadata)
    {
        $collection = new Collection($object);
        $collection->setCollectionName($metadata->getCollectionName());
        $collection->setCollectionRoute($metadata->getRoute());
        $collection->setResourceRoute($metadata->getResourceRoute());
        $collection->setRouteIdentifierName($metadata->getRouteIdentifierName());
        $collection->setEntityIdentifierName($metadata->getEntityIdentifierName());

        $links = $collection->getLinks();
        $this->marshalMetadataLinks($metadata, $links);

        if (!$links->has('self')
            && ($metadata->hasUrl() || $metadata->hasRoute())
        ) {
            $link = $this->marshalSelfLinkFromMetadata($metadata, $object);
            $links->add($link);
        }

        return $collection;
    }

    /**
     * Inject a "self" relational link based on the route and identifier
     *
     * @param  LinkCollectionAwareInterface $resource
     * @param  string $route
     * @param  string $identifier
     */
    public function injectSelfLink(LinkCollectionAwareInterface $resource, $route, $routeIdentifier = 'id')
    {
        $links = $resource->getLinks();
        if ($links->has('self')) {
            return;
        }

        $self = new Link('self');
        $self->setRoute($route);

        $routeParams  = array();
        $routeOptions = array();
        if ($resource instanceof Resource) {
            $routeParams = array(
                $routeIdentifier => $resource->id,
            );
        }
        if ($resource instanceof Collection) {
            $routeParams  = $resource->getCollectionRouteParams();
            $routeOptions = $resource->getCollectionRouteOptions();
        }

        if (!empty($routeParams)) {
            $self->setRouteParams($routeParams);
        }
        if (!empty($routeOptions)) {
            $self->setRouteOptions($routeOptions);
        }

        $links->add($self, true);
    }

    /**
     * Generate HAL links for a paginated collection
     *
     * @param  Collection $halCollection
     * @return array
     */
    protected function injectPaginationLinks(Collection $halCollection)
    {
        $collection = $halCollection->getCollection();
        $page       = $halCollection->getPage();
        $pageSize   = $halCollection->getPageSize();
        $route      = $halCollection->getCollectionRoute();
        $params     = $halCollection->getCollectionRouteParams();
        $options    = $halCollection->getCollectionRouteOptions();

        $collection->setItemCountPerPage($pageSize);
        $collection->setCurrentPageNumber($page);

        $count = count($collection);
        if (!$count) {
            return true;
        }

        if ($page < 1 || $page > $count) {
            return new ApiProblem(409, 'Invalid page provided');
        }

        $links = $halCollection->getLinks();
        $next  = ($page < $count) ? $page + 1 : false;
        $prev  = ($page > 1)      ? $page - 1 : false;

        // self link
        $link = new Link('self');
        $link->setRoute($route);
        $link->setRouteParams($params);
        $link->setRouteOptions(ArrayUtils::merge($options, array(
            'query' => array('page' => $page))
        ));
        $links->add($link, true);

        // first link
        $link = new Link('first');
        $link->setRoute($route);
        $link->setRouteParams($params);
        $link->setRouteOptions($options);
        $links->add($link);

        // last link
        $link = new Link('last');
        $link->setRoute($route);
        $link->setRouteParams($params);
        $link->setRouteOptions(ArrayUtils::merge($options, array(
            'query' => array('page' => $count))
        ));
        $links->add($link);

        // prev link
        if ($prev) {
            $link = new Link('prev');
            $link->setRoute($route);
            $link->setRouteParams($params);
            $link->setRouteOptions(ArrayUtils::merge($options, array(
                'query' => array('page' => $prev))
            ));
            $links->add($link);
        }

        // next link
        if ($next) {
            $link = new Link('next');
            $link->setRoute($route);
            $link->setRouteParams($params);
            $link->setRouteOptions(ArrayUtils::merge($options, array(
                'query' => array('page' => $next))
            ));
            $links->add($link);
        }

        return true;
    }

    /**
     * Extracts and renders a Resource and embeds it in the parent
     * representation
     *
     * Removes the key from the parent representation, and creates a
     * representation for the key in the _embedded object.
     *
     * @param  array $parent
     * @param  string $key
     * @param  Resource $resource
     */
    protected function extractEmbeddedResource(array &$parent, $key, Resource $resource)
    {
        $rendered = $this->renderResource($resource);
        if (!isset($parent['_embedded'])) {
            $parent['_embedded'] = array();
        }
        $parent['_embedded'][$key] = $rendered;
        unset($parent[$key]);
    }

    /**
     * Extracts and renders a Collection and embeds it in the parent
     * representation
     *
     * Removes the key from the parent representation, and creates a
     * representation for the key in the _embedded object.
     *
     * @param  array $parent
     * @param  string $key
     * @param  Collection $collection
     */
    protected function extractEmbeddedCollection(array &$parent, $key, Collection $collection)
    {
        $rendered = $this->extractCollection($collection);
        if (!isset($parent['_embedded'])) {
            $parent['_embedded'] = array();
        }
        $parent['_embedded'][$key] = $rendered;
        unset($parent[$key]);
    }

    /**
     * Extract a collection as an array
     *
     * @param  Collection $halCollection
     * @return array
     */
    protected function extractCollection(Collection $halCollection)
    {
        $collection           = array();
        $events               = $this->getEventManager();
        $routeIdentifierName  = $halCollection->getRouteIdentifierName();
        $resourceRoute        = $halCollection->getResourceRoute();
        $resourceRouteParams  = $halCollection->getResourceRouteParams();
        $resourceRouteOptions = $halCollection->getResourceRouteOptions();
        $metadataMap          = $this->getMetadataMap();

        foreach ($halCollection->getCollection() as $resource) {
            $eventParams = new ArrayObject(array(
                'collection'   => $halCollection,
                'resource'     => $resource,
                'route'        => $resourceRoute,
                'routeParams'  => $resourceRouteParams,
                'routeOptions' => $resourceRouteOptions,
            ));
            $events->trigger('renderCollection.resource', $this, $eventParams);

            $resource = $eventParams['resource'];

            if (is_object($resource) && $metadataMap->has($resource)) {
                $resource = $this->createResourceFromMetadata($resource, $metadataMap->get($resource));
            }

            if ($resource instanceof Resource) {
                $collection[] = $this->renderResource($resource, $this->getRenderCollections());
                continue;
            }

            if (!is_array($resource)) {
                $resource = $this->convertResourceToArray($resource);
            }

            foreach ($resource as $key => $value) {
                if (is_object($value) && $metadataMap->has($value)) {
                    $value = $this->createResourceFromMetadata($value, $metadataMap->get($value));
                }

                if ($value instanceof Resource) {
                    $this->extractEmbeddedResource($resource, $key, $value);
                }
                if ($value instanceof Collection) {
                    $this->extractEmbeddedCollection($resource, $key, $value);
                }
            }

            $id = $this->getIdFromResource($resource);
            if (!$id) {
                // Cannot handle resources without an identifier
                // Return as-is
                $collection[] = $resource;
                continue;
            }

            if ($eventParams['resource'] instanceof LinkCollectionAwareInterface) {
                $links = $eventParams['resource']->getLinks();
            } else {
                $links = new LinkCollection();
            }

            $selfLink = new Link('self');
            $selfLink->setRoute(
                $eventParams['route'],
                array_merge($eventParams['routeParams'], array($routeIdentifierName => $id)),
                $eventParams['routeOptions']
            );
            $links->add($selfLink);

            $resource['_links'] = $this->fromLinkCollection($links);

            $collection[] = $resource;
        }

        return $collection;
    }

    /**
     * Retrieve the identifier from a resource
     *
     * Expects an "id" member to exist; if not, a boolean false is returned.
     *
     * Triggers the "getIdFromResource" event with the resource; listeners can
     * return a non-false, non-null value in order to specify the identifier
     * to use for URL assembly.
     *
     * @param  array|object $resource
     * @return mixed|false
     */
    protected function getIdFromResource($resource)
    {
        $results = $this->getEventManager()->trigger(
            __FUNCTION__,
            $this,
            array('resource' => $resource),
            function ($r) {
                return (null !== $r && false !== $r);
            }
        );

        if ($results->stopped()) {
            return $results->last();
        }

        return false;
    }

    /**
     * Convert an individual resource to an array
     *
     * @param  object $resource
     * @return array
     */
    protected function convertResourceToArray($resource)
    {
        $hydrator = $this->getHydratorForResource($resource);
        if (!$hydrator) {
            return (array) $resource;
        }

        return $hydrator->extract($resource);
    }

    /**
     * Creates a link object, given metadata and a resource
     *
     * @param  Metadata $metadata
     * @param  object $object
     * @param  null|string $id
     * @param  null|string $routeIdentifierName
     * @return Link
     * @throws Exception\RuntimeException
     */
    protected function marshalSelfLinkFromMetadata(Metadata $metadata, $object, $id = null, $routeIdentifierName = null)
    {
        $link = new Link('self');
        if ($metadata->hasUrl()) {
            $link->setUrl($metadata->getUrl());
            return $link;
        }

        if (!$metadata->hasRoute()) {
            throw new Exception\RuntimeException(sprintf(
                'Unable to create a self link for resource of type "%s"; metadata does not contain a route or a url',
                get_class($object)
            ));
        }

        $params = $metadata->getRouteParams();
        if ($routeIdentifierName) {
            $params = array_merge($params, array($routeIdentifierName => $id));
        }

        $link->setRoute($metadata->getRoute(), $params, $metadata->getRouteOptions());
        return $link;
    }

    /**
     * Inject any links found in the metadata into the resource's link collection
     *
     * @param  Metadata $metadata
     * @param  LinkCollection $links
     */
    protected function marshalMetadataLinks(Metadata $metadata, LinkCollection $links)
    {
        foreach ($metadata->getLinks() as $linkData) {
            $link = Link::factory($linkData);
            $links->add($link);
        }
    }
}
