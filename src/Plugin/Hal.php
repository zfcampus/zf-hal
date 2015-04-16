<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Plugin;

use ArrayObject;
use Closure;
use Countable;
use JsonSerializable;
use SplObjectStorage;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\Plugin\PluginInterface as ControllerPluginInterface;
use Zend\Paginator\Paginator;
use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\DispatchableInterface;
use Zend\Stdlib\Extractor\ExtractionInterface;
use Zend\Stdlib\Hydrator\HydratorPluginManager;
use Zend\View\Helper\AbstractHelper;
use Zend\View\Helper\ServerUrl;
use Zend\View\Helper\Url;
use ZF\ApiProblem\ApiProblem;
use ZF\ApiProblem\Exception\DomainException;
use ZF\Hal\Entity;
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
     * Default hydrator to use if no hydrator found for a specific entity class.
     *
     * @var ExtractionInterface
     */
    protected $defaultHydrator;

    /**
     * Boolean to render embedded entities or just include _embedded data
     *
     * @var boolean
     */
    protected $renderEmbeddedEntities = true;

    /**
     * Boolean to render collections or just return their _embedded data
     *
     * @var boolean
     */
    protected $renderCollections = true;

    /**
     * Map of entities to their ZF\Hal\Entity serializations
     *
     * @var SplObjectStorage
     */
    protected $serializedEntities;

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
     * Server url
     *
     * @var string
     */
    protected $serverUrlString;

    /**
     * @var Url
     */
    protected $urlHelper;

    /**
     * Entities spl hash stack for circular reference detection
     *
     * @var array
     */
    protected $entityHashStack = array();

    /**
     * @param null|HydratorPluginManager $hydrators
     */
    public function __construct(HydratorPluginManager $hydrators = null)
    {
        if (null === $hydrators) {
            $hydrators = new HydratorPluginManager();
        }
        $this->hydrators = $hydrators;

        $this->serializedEntities = new SplObjectStorage();
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
     * @return self
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            __CLASS__,
            get_class($this),
        ));
        $this->events = $events;

        $events->attach('getIdFromEntity', function ($e) {
            $entity = $e->getParam('entity');

            // Found id in array
            if (is_array($entity) && array_key_exists('id', $entity)) {
                return $entity['id'];
            }

            // No id in array, or not an object; return false
            if (is_array($entity) || !is_object($entity)) {
                return false;
            }

            // Found public id property on object
            if (isset($entity->id)) {
                return $entity->id;
            }

            // Found public id getter on object
            if (method_exists($entity, 'getid')) {
                return $entity->getId();
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
     * @return self
     */
    public function setServerUrlHelper(ServerUrl $helper)
    {
        $this->serverUrlHelper = $helper;
        return $this;
    }

    /**
     * @param Url $helper
     * @return self
     */
    public function setUrlHelper(Url $helper)
    {
        $this->urlHelper = $helper;
        return $this;
    }

    /**
     * Map an entity class to a specific hydrator instance
     *
     * @param  string $class
     * @param  ExtractionInterface $hydrator
     * @return self
     */
    public function addHydrator($class, $hydrator)
    {
        if (!$hydrator instanceof ExtractionInterface) {
            $hydrator = $this->hydrators->get($hydrator);
        }

        $class = strtolower($class);
        $this->hydratorMap[$class] = $hydrator;
        return $this;
    }

    /**
     * Set the default hydrator to use if none specified for a class.
     *
     * @param  ExtractionInterface $hydrator
     * @return self
     */
    public function setDefaultHydrator(ExtractionInterface $hydrator)
    {
        $this->defaultHydrator = $hydrator;
        return $this;
    }

    /**
     * Set boolean to render embedded entities or just include _embedded data
     *
     * @deprecated
     * @param  boolean $value
     * @return self
     */
    public function setRenderEmbeddedResources($value)
    {
        trigger_error(sprintf(
            '%s has been deprecated; please use %s::setRenderEmbeddedEntities',
            __METHOD__,
            __CLASS__
        ), E_USER_DEPRECATED);
        $this->renderEmbeddedEntities = $value;
        return $this;
    }

    /**
     * Set boolean to render embedded entities or just include _embedded data
     *
     * @param  boolean $value
     * @return self
     */
    public function setRenderEmbeddedEntities($value)
    {
        $this->renderEmbeddedEntities = $value;
        return $this;
    }

    /**
     * Get boolean to render embedded resources or just include _embedded data
     *
     * @deprecated
     * @return boolean
     */
    public function getRenderEmbeddedResources()
    {
        trigger_error(sprintf(
            '%s has been deprecated; please use %s::getRenderEmbeddedEntities',
            __METHOD__,
            __CLASS__
        ), E_USER_DEPRECATED);
        return $this->renderEmbeddedEntities;
    }

    /**
     * Get boolean to render embedded entities or just include _embedded data
     *
     * @return boolean
     */
    public function getRenderEmbeddedEntities()
    {
        return $this->renderEmbeddedEntities;
    }

    /**
     * Set boolean to render embedded collections or just include _embedded data
     *
     * @param  boolean $value
     * @return self
     */
    public function setRenderCollections($value)
    {
        $this->renderCollections = $value;
        return $this;
    }

    /**
     * Get boolean to render embedded collections or just include _embedded data
     *
     * @return boolean
     */
    public function getRenderCollections()
    {
        return $this->renderCollections;
    }

    /**
     * Retrieve a hydrator for a given entity
     *
     * Please use getHydratorForEntity().
     *
     * @deprecated
     * @param  object $resource
     * @return ExtractionInterface|false
     */
    public function getHydratorForResource($resource)
    {
        trigger_error(sprintf(
            '%s is deprecated; please use %s::getHydratorForEntity',
            __METHOD__,
            __CLASS__
        ), E_USER_DEPRECATED);
        return self::getHydratorForEntity($resource);
    }

    /**
     * Retrieve a hydrator for a given entity
     *
     * If the entity has a mapped hydrator, returns that hydrator. If not, and
     * a default hydrator is present, the default hydrator is returned.
     * Otherwise, a boolean false is returned.
     *
     * @param  object $entity
     * @return ExtractionInterface|false
     */
    public function getHydratorForEntity($entity)
    {
        $class = get_class($entity);
        $classLower = strtolower($class);

        if (isset($this->hydratorMap[$classLower])) {
            return $this->hydratorMap[$classLower];
        }

        $metadataMap = $this->getMetadataMap();
        if ($metadataMap->has($entity)) {
            $metadata = $metadataMap->get($class);
            $hydrator = $metadata->getHydrator();
            if ($hydrator instanceof ExtractionInterface) {
                $this->addHydrator($class, $hydrator);
                return $hydrator;
            }
        }

        if ($this->defaultHydrator instanceof ExtractionInterface) {
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
     * For each entity in the collection, the event "renderCollection.entity" is
     * triggered, with the following parameters:
     *
     * - "collection", which is the $halCollection passed to the method
     * - "entity", which is the current entity
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
     * @return array|ApiProblem Associative array representing the payload to render;
     *     returns ApiProblem if error in pagination occurs
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

        $metadataMap = $this->getMetadataMap();

        $maxDepth = is_object($collection) && $metadataMap->has($collection) ?
            $metadataMap->get($collection)->getMaxDepth() : null;

        $payload = $halCollection->getAttributes();
        $payload['_links']    = $this->fromResource($halCollection);
        $payload['_embedded'] = array(
            $collectionName => $this->extractCollection($halCollection, 0, $maxDepth),
        );

        if ($collection instanceof Paginator) {
            $payload['page_count']  = isset($payload['page_count'])
                ? $payload['page_count']
                : $collection->count();
            $payload['page_size']   = isset($payload['page_size'])
                ? $payload['page_size']
                : $halCollection->getPageSize();
            $payload['total_items'] = isset($payload['total_items'])
                ? $payload['total_items']
                : (int) $collection->getTotalItemCount();
            $payload['page'] = ($payload['page_count'] > 0)
                ? $halCollection->getPage()
                : 0;
        } elseif (is_array($collection) || $collection instanceof Countable) {
            $payload['total_items'] = isset($payload['total_items']) ? $payload['total_items'] : count($collection);
        }

        $payload = new ArrayObject($payload);
        $this->getEventManager()->trigger(
            __FUNCTION__ . '.post',
            $this,
            array('payload' => $payload, 'collection' => $halCollection)
        );

        return (array) $payload;
    }

    /**
     * Deprecated: render an individual entity
     *
     * This method exists for pre-0.9.0 consumers, and ensures the
     * renderResource event is triggered, before proxing to the renderEntity()
     * method.
     *
     * @deprecated
     * @param  Resource $halResource
     * @param  bool $renderResource
     * @return array
     */
    public function renderResource(Resource $halResource, $renderResource = true, $depth = 0)
    {
        trigger_error(sprintf(
            'The method %s is deprecated; please use %s::renderEntity()',
            __METHOD__,
            __CLASS__
        ), E_USER_DEPRECATED);
        $this->getEventManager()->trigger(__FUNCTION__, $this, array('resource' => $halResource));

        return $this->renderEntity($halResource, $renderResource, $depth + 1);
    }

    /**
     * Render an individual entity
     *
     * Creates a hash representation of the Entity. The entity is first
     * converted to an array, and its associated links are injected as the
     * "_links" member. If any members of the entity are themselves
     * Entity objects, they are extracted into an "_embedded" hash.
     *
     * @param  Entity $halEntity
     * @param  bool $renderEntity
     * @param  int $depth           depth of the current rendering recursion
     * @param  int $maxDepth        maximum rendering depth for the current metadata
     * @throws Exception\CircularReferenceException
     * @return array
     */
    public function renderEntity(Entity $halEntity, $renderEntity = true, $depth = 0, $maxDepth = null)
    {
        $this->getEventManager()->trigger(__FUNCTION__, $this, array('entity' => $halEntity));
        $entity      = $halEntity->entity;
        $entityLinks = $halEntity->getLinks();
        $metadataMap = $this->getMetadataMap();

        if (is_object($entity)) {
            if ($maxDepth === null && $metadataMap->has($entity)) {
                $maxDepth = $metadataMap->get($entity)->getMaxDepth();
            }

            if ($maxDepth === null) {
                $entityHash = spl_object_hash($entity);

                if (isset($this->entityHashStack[$entityHash])) {
                    // we need to clear the stack, as the exception may be caught and the plugin may be invoked again
                    $this->entityHashStack = array();
                    throw new Exception\CircularReferenceException(sprintf(
                        "Circular reference detected in '%s'. %s",
                        get_class($entity),
                        "Either set a 'max_depth' metadata attribute or remove the reference"
                    ));
                }

                $this->entityHashStack[$entityHash] = get_class($entity);
            }
        }

        if (!$renderEntity || ($maxDepth !== null && $depth > $maxDepth)) {
            $entity = array();
        }

        if (!is_array($entity)) {
            $entity = $this->convertEntityToArray($entity);
        }

        foreach ($entity as $key => $value) {
            if (is_object($value) && $metadataMap->has($value)) {
                $value = $this->createEntityFromMetadata(
                    $value,
                    $metadataMap->get($value),
                    $this->getRenderEmbeddedEntities()
                );
            }

            if ($value instanceof Entity) {
                $this->extractEmbeddedEntity($entity, $key, $value, $depth + 1, $maxDepth);
            }
            if ($value instanceof Collection) {
                $this->extractEmbeddedCollection($entity, $key, $value, $depth + 1, $maxDepth);
            }
            if ($value instanceof Link) {
                $entityLinks->add($value);
                unset($entity[$key]);
            }
            if ($value instanceof LinkCollection) {
                array_walk_recursive($value, function ($link) use ($entityLinks) {
                    $entityLinks->add($link);
                });
                unset($entity[$key]);
            }
        }

        $entity['_links'] = $this->fromResource($halEntity);

        if (isset($entityHash)) {
            unset($this->entityHashStack[$entityHash]);
        }

        return $entity;
    }

    /**
     * Create a fully qualified URI for a link
     *
     * Triggers the "createLink" event with the route, id, entity, and a set of
     * params that will be passed to the route; listeners can alter any of the
     * arguments, which will then be used by the method to generate the url.
     *
     * @todo   Remove 'resource' from the event parameters prior to 1.0.0.
     * @param  string $route
     * @param  null|false|int|string $id
     * @param  null|mixed $entity
     * @return string
     */
    public function createLink($route, $id = null, $entity = null)
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
            'entity'   => $entity,
            'resource' => $entity,
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
     * @throws DomainException
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
            $representation['href'] = $linkDefinition->getUrl();

            return $representation;
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
            $representation['href'] = $path;
        } else {
            $representation['href'] = $this->getServerUrl() . $path;
        }
        return $representation;
    }

    /**
     * Generate HAL links from a LinkCollection
     *
     * @param  LinkCollection $collection
     * @return array
     * @throws DomainException
     */
    public function fromLinkCollection(LinkCollection $collection)
    {
        $links = array();
        foreach ($collection as $rel => $linkDefinition) {
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
     * Create HAL links "object" from an entity or collection
     *
     * @param  LinkCollectionAwareInterface $resource
     * @return array
     */
    public function fromResource(LinkCollectionAwareInterface $resource)
    {
        return $this->fromLinkCollection($resource->getLinks());
    }

    /**
     * Create a entity and/or collection based on a metadata map
     *
     * Deprecated; please use createEntityFromMetadata().
     *
     * @deprecated
     * @param  object $object
     * @param  Metadata $metadata
     * @param  bool $renderEmbeddedEntities
     * @return Entity|Collection
     */
    public function createResourceFromMetadata($object, Metadata $metadata, $renderEmbeddedEntities = true)
    {
        trigger_error(sprintf(
            '%s is deprecated; please use %s::createEntityFromMetadata',
            __METHOD__,
            __CLASS__
        ), E_USER_DEPRECATED);
        return $this->createEntityFromMetadata($object, $metadata, $renderEmbeddedEntities);
    }

    /**
     * Create a entity and/or collection based on a metadata map
     *
     * @param  object $object
     * @param  Metadata $metadata
     * @param  bool $renderEmbeddedEntities
     * @return Entity|Collection
     * @throws Exception\RuntimeException
     */
    public function createEntityFromMetadata($object, Metadata $metadata, $renderEmbeddedEntities = true)
    {
        if ($metadata->isCollection()) {
            return $this->createCollectionFromMetadata($object, $metadata);
        }

        $data = $this->convertEntityToArray($object);

        $entityIdentifierName = $metadata->getEntityIdentifierName();
        if ($entityIdentifierName && ! isset($data[$entityIdentifierName])) {
            throw new Exception\RuntimeException(sprintf(
                'Unable to determine entity identifier for object of type "%s"; no fields matching "%s"',
                get_class($object),
                $entityIdentifierName
            ));
        }

        $id = ($entityIdentifierName) ? $data[$entityIdentifierName]: null;

        if (!$renderEmbeddedEntities) {
            $object = array();
        }

        $halEntity = new Entity($object, $id);

        $links = $halEntity->getLinks();
        $this->marshalMetadataLinks($metadata, $links);

        if (!$links->has('self')) {
            $link = $this->marshalLinkFromMetadata($metadata, $object, $id, $metadata->getRouteIdentifierName());
            $links->add($link);
        }

        return $halEntity;
    }

    /**
     * Create an Entity instance and inject it with a self relational link
     *
     * Deprecated; please use createEntity().
     *
     * @deprecated
     * @param  Entity|array|object $resource
     * @param  string $route
     * @param  string $routeIdentifierName
     * @return Entity
     */
    public function createResource($resource, $route, $routeIdentifierName)
    {
        trigger_error(sprintf(
            '%s is deprecated; use %s::createEntity instead',
            __METHOD__,
            __CLASS__
        ), E_USER_DEPRECATED);
        return $this->createEntity($resource, $route, $routeIdentifierName);
    }

    /**
     * Create an Entity instance and inject it with a self relational link
     *
     * @param  Entity|array|object $entity
     * @param  string $route
     * @param  string $routeIdentifierName
     * @return Entity
     */
    public function createEntity($entity, $route, $routeIdentifierName)
    {
        $metadataMap = $this->getMetadataMap();
        switch (true) {
            case (is_object($entity) && $metadataMap->has($entity)):
                $generatedEntity = $this->createEntityFromMetadata($entity, $metadataMap->get($entity));
                $halEntity = new Entity($entity, $generatedEntity->id);
                $halEntity->setLinks($generatedEntity->getLinks());
                break;

            case (! $entity instanceof Entity):
                $id = $this->getIdFromEntity($entity) ?: null;
                $halEntity = new Entity($entity, $id);
                break;

            case ($entity instanceof Entity):
            default:
                $halEntity = $entity; // as is
                break;
        }

        $this->injectSelfLink($halEntity, $route, $routeIdentifierName);
        return $halEntity;
    }

    /**
     * Creates a Collection instance with a self relational link
     *
     * @param  Collection|array|object $collection
     * @param  null|string $route
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
        $collection->setEntityRoute($metadata->getEntityRoute());
        $collection->setRouteIdentifierName($metadata->getRouteIdentifierName());
        $collection->setEntityIdentifierName($metadata->getEntityIdentifierName());

        $links = $collection->getLinks();
        $this->marshalMetadataLinks($metadata, $links);

        if (!$links->has('self')
            && ($metadata->hasUrl() || $metadata->hasRoute())
        ) {
            $link = $this->marshalLinkFromMetadata($metadata, $object);
            $links->add($link);
        }

        return $collection;
    }

    /**
     * Inject a "self" relational link based on the route and identifier
     *
     * @param  LinkCollectionAwareInterface $resource
     * @param  string $route
     * @param  string $routeIdentifier
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
        if ($resource instanceof Entity
            && null !== $resource->id
        ) {
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
     * @return boolean
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
            'query' => array('page' => $page),
        )));
        $links->add($link, true);

        // first link
        $link = new Link('first');
        $link->setRoute($route);
        $link->setRouteParams($params);
        $link->setRouteOptions(ArrayUtils::merge($options, array(
            'query' => array('page' => null),
        )));
        $links->add($link);

        // last link
        $link = new Link('last');
        $link->setRoute($route);
        $link->setRouteParams($params);
        $link->setRouteOptions(ArrayUtils::merge($options, array(
            'query' => array('page' => $count),
        )));
        $links->add($link);

        // prev link
        if ($prev) {
            $link = new Link('prev');
            $link->setRoute($route);
            $link->setRouteParams($params);
            $link->setRouteOptions(ArrayUtils::merge($options, array(
                'query' => array('page' => $prev),
            )));
            $links->add($link);
        }

        // next link
        if ($next) {
            $link = new Link('next');
            $link->setRoute($route);
            $link->setRouteParams($params);
            $link->setRouteOptions(ArrayUtils::merge($options, array(
                'query' => array('page' => $next),
            )));
            $links->add($link);
        }

        return true;
    }

    /**
     * Extracts and renders an Entity and embeds it in the parent
     * representation
     *
     * Removes the key from the parent representation, and creates a
     * representation for the key in the _embedded object.
     *
     * @param  array $parent
     * @param  string $key
     * @param  Entity $entity
     * @param  int $depth           depth of the current rendering recursion
     * @param  int $maxDepth        maximum rendering depth for the current metadata
     */
    protected function extractEmbeddedEntity(array &$parent, $key, Entity $entity, $depth = 0, $maxDepth = null)
    {
        // No need to increment depth for this call
        $rendered = $this->renderEntity($entity, true, $depth, $maxDepth);

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
     * @param  array      $parent
     * @param  string     $key
     * @param  Collection $collection
     * @param  int        $depth        depth of the current rendering recursion
     * @param  int        $maxDepth     maximum rendering depth for the current metadata
     */
    protected function extractEmbeddedCollection(
        array &$parent,
        $key,
        Collection $collection,
        $depth = 0,
        $maxDepth = null
    ) {
        $rendered = $this->extractCollection($collection, $depth + 1, $maxDepth);

        if (!isset($parent['_embedded'])) {
            $parent['_embedded'] = array();
        }

        $parent['_embedded'][$key] = $rendered;
        unset($parent[$key]);
    }

    /**
     * Extract a collection as an array
     *
     * @todo   Remove 'resource' from event parameters for 1.0.0
     * @todo   Remove trigger of 'renderCollection.resource' for 1.0.0
     * @param  Collection $halCollection
     * @param  int $depth                   depth of the current rendering recursion
     * @param  int $maxDepth                maximum rendering depth for the current metadata
     * @return array
     */
    protected function extractCollection(Collection $halCollection, $depth = 0, $maxDepth = null)
    {
        $collection           = array();
        $events               = $this->getEventManager();
        $routeIdentifierName  = $halCollection->getRouteIdentifierName();
        $entityRoute          = $halCollection->getEntityRoute();
        $entityRouteParams    = $halCollection->getEntityRouteParams();
        $entityRouteOptions   = $halCollection->getEntityRouteOptions();
        $metadataMap          = $this->getMetadataMap();

        foreach ($halCollection->getCollection() as $entity) {
            $eventParams = new ArrayObject(array(
                'collection'   => $halCollection,
                'entity'       => $entity,
                'resource'     => $entity,
                'route'        => $entityRoute,
                'routeParams'  => $entityRouteParams,
                'routeOptions' => $entityRouteOptions,
            ));
            $events->trigger('renderCollection.resource', $this, $eventParams);
            $events->trigger('renderCollection.entity', $this, $eventParams);

            $entity = $eventParams['entity'];

            if (is_object($entity) && $metadataMap->has($entity)) {
                $entity = $this->createEntityFromMetadata($entity, $metadataMap->get($entity));
            }

            if ($entity instanceof Entity) {
                // Depth does not increment at this level
                $collection[] = $this->renderEntity($entity, $this->getRenderCollections(), $depth, $maxDepth);
                continue;
            }

            if (!is_array($entity)) {
                $entity = $this->convertEntityToArray($entity);
            }

            foreach ($entity as $key => $value) {
                if (is_object($value) && $metadataMap->has($value)) {
                    $value = $this->createEntityFromMetadata($value, $metadataMap->get($value));
                }

                if ($value instanceof Entity) {
                    $this->extractEmbeddedEntity($entity, $key, $value, $depth + 1, $maxDepth);
                }

                if ($value instanceof Collection) {
                    $this->extractEmbeddedCollection($entity, $key, $value, $depth + 1, $maxDepth);
                }
            }

            $id = $this->getIdFromEntity($entity);

            if ($id === false) {
                // Cannot handle entities without an identifier
                // Return as-is
                $collection[] = $entity;
                continue;
            }

            if ($eventParams['entity'] instanceof LinkCollectionAwareInterface) {
                $links = $eventParams['entity']->getLinks();
            } else {
                $links = new LinkCollection();
            }

            if (isset($entity['links']) && $entity['links'] instanceof LinkCollection) {
                $links = $entity['links'];
            }

            $selfLink = new Link('self');
            $selfLink->setRoute(
                $eventParams['route'],
                array_merge($eventParams['routeParams'], array($routeIdentifierName => $id)),
                $eventParams['routeOptions']
            );
            $links->add($selfLink);

            $entity['_links'] = $this->fromLinkCollection($links);

            $collection[] = $entity;
        }

        return $collection;
    }

    /**
     * Retrieve the identifier from an entity
     *
     * Expects an "id" member to exist; if not, a boolean false is returned.
     *
     * Triggers the "getIdFromEntity" event with the entity; listeners can
     * return a non-false, non-null value in order to specify the identifier
     * to use for URL assembly.
     *
     * @todo   Remove 'resource' from parameters sent to event for 1.0.0
     * @todo   Remove trigger of getIdFromResource for 1.0.0
     * @param  array|object $entity
     * @return mixed|false
     */
    protected function getIdFromEntity($entity)
    {
        $params  = array(
            'entity'   => $entity,
            'resource' => $entity
        );

        $callback = function ($r) {
            return (null !== $r && false !== $r);
        };

        $results = $this->getEventManager()->trigger(
            __FUNCTION__,
            $this,
            $params,
            $callback
        );

        if ($results->stopped()) {
            return $results->last();
        }

        $results = $this->getEventManager()->trigger(
            'getIdFromResource',
            $this,
            $params,
            $callback
        );

        if ($results->stopped()) {
            return $results->last();
        }

        return false;
    }

    /**
     * Return server url
     *
     * @return string
     */
    protected function getServerUrl()
    {
        if ($this->serverUrlString === null) {
            $this->serverUrlString = call_user_func($this->serverUrlHelper);
        }
        return $this->serverUrlString;
    }

    /**
     * Convert an individual entity to an array
     *
     * @param  object $entity
     * @return array
     */
    protected function convertEntityToArray($entity)
    {
        if (isset($this->serializedEntities[$entity])) {
            return $this->serializedEntities[$entity];
        }

        $array    = false;
        $hydrator = $this->getHydratorForEntity($entity);

        if ($hydrator) {
            $array = $hydrator->extract($entity);
        }

        if (false === $array && $entity instanceof JsonSerializable) {
            $array = $entity->jsonSerialize();
        }

        if (false === $array) {
            $array = get_object_vars($entity);
        }

        $this->serializedEntities[$entity] = $array;

        return $array;
    }

    /**
     * Creates a link object, given metadata and a resource
     *
     * @param  Metadata $metadata
     * @param  object $object
     * @param  null|string $id
     * @param  null|string $routeIdentifierName
     * @param  string $relation
     * @return Link
     * @throws Exception\RuntimeException
     */
    protected function marshalLinkFromMetadata(
        Metadata $metadata,
        $object,
        $id = null,
        $routeIdentifierName = null,
        $relation = 'self'
    ) {
        $link = new Link($relation);
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

        // process any callbacks
        foreach ($params as $key => $param) {
            // bind to the object if supported
            if ($param instanceof Closure
                && version_compare(PHP_VERSION, '5.4.0') >= 0
            ) {
                $param = $param->bindTo($object);
            }

            // pass the object for callbacks and non-bound closures
            if (is_callable($param)) {
                $params[$key] = call_user_func_array($param, array($object));
            }
        }

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
