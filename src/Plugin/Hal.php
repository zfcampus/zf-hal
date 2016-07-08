<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Plugin;

use ArrayObject;
use Countable;
use Zend\EventManager\Event;
use Zend\EventManager\EventInterface;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerAwareTrait;
use Zend\EventManager\EventManagerInterface;
use Zend\Hydrator\ExtractionInterface;
use Zend\Hydrator\HydratorPluginManager;
use Zend\Mvc\Controller\Plugin\PluginInterface as ControllerPluginInterface;
use Zend\Paginator\Paginator;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\DispatchableInterface;
use Zend\View\Helper\AbstractHelper;
use ZF\ApiProblem\ApiProblem;
use ZF\Hal\Collection;
use ZF\Hal\Entity;
use ZF\Hal\EntityHydratorManager;
use ZF\Hal\Exception;
use ZF\Hal\Extractor\EntityExtractor;
use ZF\Hal\Extractor\LinkCollectionExtractorInterface;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkCollection;
use ZF\Hal\Link\LinkCollectionAwareInterface;
use ZF\Hal\Link\LinkUrlBuilder;
use ZF\Hal\Link\PaginationInjector;
use ZF\Hal\Link\PaginationInjectorInterface;
use ZF\Hal\Link\SelfLinkInjector;
use ZF\Hal\Link\SelfLinkInjectorInterface;
use ZF\Hal\Metadata\Metadata;
use ZF\Hal\Metadata\MetadataMap;
use ZF\Hal\Resource;
use ZF\Hal\ResourceFactory;

/**
 * Generate links for use with HAL payloads
 */
class Hal extends AbstractHelper implements
    ControllerPluginInterface,
    EventManagerAwareInterface
{
    use EventManagerAwareTrait;

    /**
     * @var DispatchableInterface
     */
    protected $controller;

    /**
     * @var ResourceFactory
     */
    protected $resourceFactory;

    /**
     * @var EntityHydratorManager
     */
    protected $entityHydratorManager;

    /**
     * @var EntityExtractor
     */
    protected $entityExtractor;

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
     * @var HydratorPluginManager
     */
    protected $hydrators;

    /**
     * @var MetadataMap
     */
    protected $metadataMap;

    /**
     * @var PaginationInjectorInterface
     */
    protected $paginationInjector;

    /**
     * @var SelfLinkInjectorInterface
     */
    protected $selfLinkInjector;

    /**
     * @var LinkUrlBuilder
     */
    protected $linkUrlBuilder;

    /**
     * @var LinkCollectionExtractorInterface
     */
    protected $linkCollectionExtractor;

    /**
     * Entities spl hash stack for circular reference detection
     *
     * @var array
     */
    protected $entityHashStack = [];

    /**
     * @param null|HydratorPluginManager $hydrators
     */
    public function __construct(HydratorPluginManager $hydrators = null)
    {
        if (null === $hydrators) {
            $hydrators = new HydratorPluginManager(new ServiceManager());
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
     * Set the event manager instance
     *
     * @param  EventManagerInterface $events
     * @return self
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers([
            __CLASS__,
            get_class($this),
        ]);
        $this->events = $events;

        $events->attach('getIdFromEntity', function (EventInterface $e) {
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
     * @return ResourceFactory
     */
    public function getResourceFactory()
    {
        if (! $this->resourceFactory instanceof ResourceFactory) {
            $this->resourceFactory = new ResourceFactory(
                $this->getEntityHydratorManager(),
                $this->getEntityExtractor()
            );
        }
        return $this->resourceFactory;
    }

    /**
     * @param  ResourceFactory $factory
     * @return self
     */
    public function setResourceFactory(ResourceFactory $factory)
    {
        $this->resourceFactory = $factory;
        return $this;
    }

    /**
     * @return EntityHydratorManager
     */
    public function getEntityHydratorManager()
    {
        if (! $this->entityHydratorManager instanceof EntityHydratorManager) {
            $this->entityHydratorManager = new EntityHydratorManager(
                $this->hydrators,
                $this->getMetadataMap()
            );
        }

        return $this->entityHydratorManager;
    }

    /**
     * @param  EntityHydratorManager $manager
     * @return self
     */
    public function setEntityHydratorManager(EntityHydratorManager $manager)
    {
        $this->entityHydratorManager = $manager;
        return $this;
    }

    /**
     * @return EntityExtractor
     */
    public function getEntityExtractor()
    {
        if (! $this->entityExtractor instanceof EntityExtractor) {
            $this->entityExtractor = new EntityExtractor(
                $this->getEntityHydratorManager()
            );
        }

        return $this->entityExtractor;
    }

    /**
     * @param  EntityExtractor $extractor
     * @return self
     */
    public function setEntityExtractor(EntityExtractor $extractor)
    {
        $this->entityExtractor = $extractor;
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
     * @return MetadataMap
     */
    public function getMetadataMap()
    {
        if (! $this->metadataMap instanceof MetadataMap) {
            $this->setMetadataMap(new MetadataMap());
        }

        return $this->metadataMap;
    }

    /**
     * @param  MetadataMap $map
     * @return self
     */
    public function setMetadataMap(MetadataMap $map)
    {
        $this->metadataMap = $map;
        return $this;
    }

    /**
     * @param  LinkUrlBuilder $builder
     * @return self
     */
    public function setLinkUrlBuilder(LinkUrlBuilder $builder)
    {
        $this->linkUrlBuilder = $builder;
        return $this;
    }

    /**
     * @deprecated Since 1.4.0; use setLinkUrlBuilder() instead.
     * @param callable $helper
     * @throws Exception\DeprecatedMethodException
     */
    public function setServerUrlHelper(callable $helper)
    {
        throw new Exception\DeprecatedMethodException(sprintf(
            '%s can no longer be used to influence URL generation; please '
            . 'use %s::setLinkUrlBuilder() instead, providing a configured '
            . '%s instance',
            __METHOD__,
            __CLASS__,
            LinkUrlBuilder::class
        ));
    }

    /**
     * @deprecated Since 1.4.0; use setLinkUrlBuilder() instead.
     * @param callable $helper
     * @throws Exception\DeprecatedMethodException
     */
    public function setUrlHelper(callable $helper)
    {
        throw new Exception\DeprecatedMethodException(sprintf(
            '%s can no longer be used to influence URL generation; please '
            . 'use %s::setLinkUrlBuilder() instead, providing a configured '
            . '%s instance',
            __METHOD__,
            __CLASS__,
            LinkUrlBuilder::class
        ));
    }

    /**
     * @return PaginationInjectorInterface
     */
    public function getPaginationInjector()
    {
        if (! $this->paginationInjector instanceof PaginationInjectorInterface) {
            $this->setPaginationInjector(new PaginationInjector());
        }
        return $this->paginationInjector;
    }

    /**
     * @param  PaginationInjectorInterface $injector
     * @return self
     */
    public function setPaginationInjector(PaginationInjectorInterface $injector)
    {
        $this->paginationInjector = $injector;
        return $this;
    }

    /**
     * @return SelfLinkInjectorInterface
     */
    public function getSelfLinkInjector()
    {
        if (! $this->selfLinkInjector instanceof SelfLinkInjectorInterface) {
            $this->setSelfLinkInjector(new SelfLinkInjector());
        }
        return $this->selfLinkInjector;
    }

    /**
     * @param  SelfLinkInjectorInterface $injector
     * @return self
     */
    public function setSelfLinkInjector(SelfLinkInjectorInterface $injector)
    {
        $this->selfLinkInjector = $injector;
        return $this;
    }

    /**
     * @return LinkCollectionExtractorInterface
     */
    public function getLinkCollectionExtractor()
    {
        return $this->linkCollectionExtractor;
    }

    /**
     * @param  LinkCollectionExtractorInterface $extractor
     * @return self
     */
    public function setLinkCollectionExtractor(LinkCollectionExtractorInterface $extractor)
    {
        $this->linkCollectionExtractor = $extractor;
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
        $this->getEntityHydratorManager()->addHydrator($class, $hydrator);
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
        $this->getEntityHydratorManager()->setDefaultHydrator($hydrator);
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
        return $this->getEntityHydratorManager()->getHydratorForEntity($entity);
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
     * $params['routeOptions']['query'] = ['format' => 'json'];
     * </code>
     *
     * @param  Collection $halCollection
     * @return array|ApiProblem Associative array representing the payload to render;
     *     returns ApiProblem if error in pagination occurs
     */
    public function renderCollection(Collection $halCollection)
    {
        $this->getEventManager()->trigger(__FUNCTION__, $this, ['collection' => $halCollection]);
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
        $payload['_embedded'] = [
            $collectionName => $this->extractCollection($halCollection, 0, $maxDepth),
        ];

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
            ['payload' => $payload, 'collection' => $halCollection]
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
        $this->getEventManager()->trigger(__FUNCTION__, $this, ['resource' => $halResource]);

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
        $this->getEventManager()->trigger(__FUNCTION__, $this, ['entity' => $halEntity]);
        $entity      = $halEntity->getEntity();
        $entityLinks = clone $halEntity->getLinks(); // Clone to prevent link duplication

        $metadataMap = $this->getMetadataMap();

        if (is_object($entity)) {
            if ($maxDepth === null && $metadataMap->has($entity)) {
                $maxDepth = $metadataMap->get($entity)->getMaxDepth();
            }

            if ($maxDepth === null) {
                $entityHash = spl_object_hash($entity);

                if (isset($this->entityHashStack[$entityHash])) {
                    // we need to clear the stack, as the exception may be caught and the plugin may be invoked again
                    $this->entityHashStack = [];
                    throw new Exception\CircularReferenceException(sprintf(
                        "Circular reference detected in '%s'. %s",
                        get_class($entity),
                        "Either set a 'max_depth' metadata attribute or remove the reference"
                    ));
                }

                $this->entityHashStack[$entityHash] = get_class($entity);
            }
        }

        if (! $renderEntity || ($maxDepth !== null && $depth > $maxDepth)) {
            $entity = [];
        }

        if (!is_array($entity)) {
            $entity = $this->getEntityExtractor()->extract($entity);
        }

        foreach ($entity as $key => $value) {
            if (is_object($value) && $metadataMap->has($value)) {
                $value = $this->getResourceFactory()->createEntityFromMetadata(
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
                // We have a link; add it to the entity if it's not already present.
                $entityLinks = $this->injectPropertyAsLink($value, $entityLinks);
                unset($entity[$key]);
            }
            if ($value instanceof LinkCollection) {
                foreach ($value as $link) {
                    $entityLinks = $this->injectPropertyAsLink($link, $entityLinks);
                }
                unset($entity[$key]);
            }
        }

        $halEntity->setLinks($entityLinks);
        $entity['_links'] = $this->fromResource($halEntity);

        $payload = new ArrayObject($entity);
        $this->getEventManager()->trigger(
            __FUNCTION__ . '.post',
            $this,
            ['payload' => $payload, 'entity' => $halEntity]
        );

        if (isset($entityHash)) {
            unset($this->entityHashStack[$entityHash]);
        }

        return $payload->getArrayCopy();
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
        $eventParams = $events->prepareArgs([
            'route'    => $route,
            'id'       => $id,
            'entity'   => $entity,
            'resource' => $entity,
            'params'   => $params,
        ]);
        $events->trigger(__FUNCTION__, $this, $eventParams);

        return $this->linkUrlBuilder->buildLinkUrl(
            $route,
            $params->getArrayCopy(),
            [],
            $reUseMatchedParams
        );
    }

    /**
     * Create a URL from a Link
     *
     * @param  Link $linkDefinition
     * @return array
     */
    public function fromLink(Link $linkDefinition)
    {
        $linkExtractor = $this->linkCollectionExtractor->getLinkExtractor();

        return $linkExtractor->extract($linkDefinition);
    }

    /**
     * Generate HAL links from a LinkCollection
     *
     * @param  LinkCollection $collection
     * @return array
     */
    public function fromLinkCollection(LinkCollection $collection)
    {
        return $this->linkCollectionExtractor->extract($collection);
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
     */
    public function createEntityFromMetadata($object, Metadata $metadata, $renderEmbeddedEntities = true)
    {
        return $this->getResourceFactory()->createEntityFromMetadata(
            $object,
            $metadata,
            $renderEmbeddedEntities
        );
    }

    /**
     * Create an Entity instance and inject it with a self relational link if necessary
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
     * Create an Entity instance and inject it with a self relational link if necessary
     *
     * @param  Entity|array|object $entity
     * @param  string $route
     * @param  string $routeIdentifierName
     * @return Entity
     */
    public function createEntity($entity, $route, $routeIdentifierName)
    {
        $metadataMap = $this->getMetadataMap();

        if (is_object($entity) && $metadataMap->has($entity)) {
            $halEntity = $this->getResourceFactory()->createEntityFromMetadata(
                $entity,
                $metadataMap->get($entity)
            );
        } elseif (! $entity instanceof Entity) {
            $id = $this->getIdFromEntity($entity) ?: null;
            $halEntity = new Entity($entity, $id);
        } else {
            $halEntity = $entity;
        }

        $metadata = (! is_array($entity) && $metadataMap->has($entity))
            ? $metadataMap->get($entity)
            : false;

        if (! $metadata || ($metadata && $metadata->getForceSelfLink())) {
            $this->injectSelfLink($halEntity, $route, $routeIdentifierName);
        }

        return $halEntity;
    }

    /**
     * Creates a Collection instance with a self relational link if necessary
     *
     * @param  Collection|array|object $collection
     * @param  null|string $route
     * @return Collection
     */
    public function createCollection($collection, $route = null)
    {
        $metadataMap = $this->getMetadataMap();
        if (is_object($collection) && $metadataMap->has($collection)) {
            $collection = $this->getResourceFactory()->createCollectionFromMetadata(
                $collection,
                $metadataMap->get($collection)
            );
        }

        if (! $collection instanceof Collection) {
            $collection = new Collection($collection);
        }

        $metadata = $metadataMap->get($collection);
        if (! $metadata || ($metadata && $metadata->getForceSelfLink())) {
            $this->injectSelfLink($collection, $route);
        }

        return $collection;
    }

    /**
     * @param  object $object
     * @param  Metadata $metadata
     * @return Collection
     */
    public function createCollectionFromMetadata($object, Metadata $metadata)
    {
        return $this->getResourceFactory()->createCollectionFromMetadata($object, $metadata);
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
        $this->getSelfLinkInjector()->injectSelfLink($resource, $route, $routeIdentifier);
    }

    /**
     * Generate HAL links for a paginated collection
     *
     * @param  Collection $halCollection
     * @return boolean|ApiProblem
     */
    protected function injectPaginationLinks(Collection $halCollection)
    {
        return $this->getPaginationInjector()->injectPaginationLinks($halCollection);
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
            $parent['_embedded'] = [];
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
            $parent['_embedded'] = [];
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
        $collection           = [];
        $events               = $this->getEventManager();
        $routeIdentifierName  = $halCollection->getRouteIdentifierName();
        $entityRoute          = $halCollection->getEntityRoute();
        $entityRouteParams    = $halCollection->getEntityRouteParams();
        $entityRouteOptions   = $halCollection->getEntityRouteOptions();
        $metadataMap          = $this->getMetadataMap();


        foreach ($halCollection->getCollection() as $entity) {
            $eventParams = new ArrayObject([
                'collection'   => $halCollection,
                'entity'       => $entity,
                'resource'     => $entity,
                'route'        => $entityRoute,
                'routeParams'  => $entityRouteParams,
                'routeOptions' => $entityRouteOptions,
            ]);
            $events->trigger('renderCollection.resource', $this, $eventParams);
            $events->trigger('renderCollection.entity', $this, $eventParams);

            $entity = $eventParams['entity'];

            if (is_object($entity) && $metadataMap->has($entity)) {
                $entity = $this->getResourceFactory()->createEntityFromMetadata($entity, $metadataMap->get($entity));
            }

            if ($entity instanceof Entity) {
                // Depth does not increment at this level
                $collection[] = $this->renderEntity($entity, $this->getRenderCollections(), $depth, $maxDepth);
                continue;
            }

            if (!is_array($entity)) {
                $entity = $this->getEntityExtractor()->extract($entity);
            }

            foreach ($entity as $key => $value) {
                if (is_object($value) && $metadataMap->has($value)) {
                    $value = $this->getResourceFactory()->createEntityFromMetadata($value, $metadataMap->get($value));
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

            /* $entity is always an array here. We don't have metadata config for arrays so the self link is forced
               by default (at the moment) and should be removed manually if not required. But at some point it should
               be discussed if it makes sense to force self links in this particular use-case.  */
            $selfLink = new Link('self');
            $selfLink->setRoute(
                $eventParams['route'],
                array_merge($eventParams['routeParams'], [$routeIdentifierName => $id]),
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
        $params  = [
            'entity'   => $entity,
            'resource' => $entity
        ];

        $callback = function ($r) {
            return (null !== $r && false !== $r);
        };

        $results = $this->getEventManager()->triggerEventUntil(
            $callback,
            new Event(__FUNCTION__, $this, $params)
        );

        if ($results->stopped()) {
            return $results->last();
        }

        $results = $this->getEventManager()->triggerEventUntil(
            $callback,
            new Event('getIdFromResource', $this, $params)
        );

        if ($results->stopped()) {
            return $results->last();
        }

        return false;
    }

    /**
     * Reset entity hash stack
     *
     * Call this method if you are rendering multiple responses within the same
     * request cycle that may encounter the same entity instances.
     *
     * @return void
     */
    public function resetEntityHashStack()
    {
        $this->entityHashStack = [];
    }

    /**
     * Convert an individual entity to an array
     *
     * @deprecated
     * @param  object $entity
     * @return array
     */
    protected function convertEntityToArray($entity)
    {
        return $this->getEntityExtractor()->extract($entity);
    }

    /**
     * Creates a link object, given metadata and a resource
     *
     * @deprecated
     * @param  Metadata $metadata
     * @param  object $object
     * @param  null|string $id
     * @param  null|string $routeIdentifierName
     * @param  string $relation
     * @return Link
     */
    protected function marshalLinkFromMetadata(
        Metadata $metadata,
        $object,
        $id = null,
        $routeIdentifierName = null,
        $relation = 'self'
    ) {
        return $this->getResourceFactory()->marshalLinkFromMetadata(
            $metadata,
            $object,
            $id,
            $routeIdentifierName,
            $relation
        );
    }

    /**
     * Inject any links found in the metadata into the resource's link collection
     *
     * @deprecated
     * @param  Metadata $metadata
     * @param  LinkCollection $links
     */
    protected function marshalMetadataLinks(Metadata $metadata, LinkCollection $links)
    {
        $this->getResourceFactory()->marshalMetadataLinks($metadata, $links);
    }

    /**
     * Inject a property-based link into the link collection.
     *
     * Ensures that the link hasn't been previously injected.
     *
     * @param Link[]|Link $link
     * @param LinkCollection $links
     * @return LinkCollection
     * @throws Exception\InvalidArgumentException if a non-link is provided.
     */
    protected function injectPropertyAsLink($link, LinkCollection $links)
    {
        if (is_array($link)) {
            foreach ($link as $single) {
                $links = $this->injectPropertyAsLink($single, $links);
            }
            return $links;
        }

        if (! $link instanceof Link) {
            throw new Exception\InvalidArgumentException(
                'Invalid link discovered; cannot inject into representation'
            );
        }

        $rel = $link->getRelation();
        if (! $links->has($rel)) {
            $links->add($link);
            return $links;
        }

        $relLink = $links->get($rel);
        if ($relLink !== $link
            || (is_array($relLink) && ! in_array($link, $relLink, true))
        ) {
            $links->add($link);
            return $links;
        }

        return $links;
    }
}
