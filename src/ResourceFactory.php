<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal;

use Closure;
use ZF\Hal\Collection;
use ZF\Hal\Entity;
use ZF\Hal\Extractor\EntityExtractor;
use ZF\Hal\Exception;
use ZF\Hal\Link\Link;
use ZF\Hal\Link\LinkCollection;
use ZF\Hal\Metadata\Metadata;

class ResourceFactory
{
    /**
     * @var EntityHydratorManager
     */
    protected $entityHydratorManager;

    /**
     * @var EntityExtractor
     */
    protected $entityExtractor;

    /**
     * @param EntityHydratorManager $entityHydratorManager
     * @param EntityExtractor $entityExtractor
     */
    public function __construct(EntityHydratorManager $entityHydratorManager, EntityExtractor $entityExtractor)
    {
        $this->entityHydratorManager = $entityHydratorManager;
        $this->entityExtractor       = $entityExtractor;
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

        $data = $this->entityExtractor->extract($object);

        $entityIdentifierName = $metadata->getEntityIdentifierName();
        if ($entityIdentifierName && ! isset($data[$entityIdentifierName])) {
            throw new Exception\RuntimeException(sprintf(
                'Unable to determine entity identifier for object of type "%s"; no fields matching "%s"',
                get_class($object),
                $entityIdentifierName
            ));
        }

        $id = ($entityIdentifierName) ? $data[$entityIdentifierName]: null;

        if (! $renderEmbeddedEntities) {
            $object = [];
        }

        $halEntity = new Entity($object, $id);

        $links = $halEntity->getLinks();
        $this->marshalMetadataLinks($metadata, $links);

        $forceSelfLink = $metadata->getForceSelfLink();
        if ($forceSelfLink && ! $links->has('self')) {
            $link = $this->marshalLinkFromMetadata(
                $metadata,
                $object,
                $id,
                $metadata->getRouteIdentifierName()
            );
            $links->add($link);
        }

        return $halEntity;
    }

    /**
     * @param  object $object
     * @param  Metadata $metadata
     * @return Collection
     */
    public function createCollectionFromMetadata($object, Metadata $metadata)
    {
        $halCollection = new Collection($object);
        $halCollection->setCollectionName($metadata->getCollectionName());
        $halCollection->setCollectionRoute($metadata->getRoute());
        $halCollection->setEntityRoute($metadata->getEntityRoute());
        $halCollection->setRouteIdentifierName($metadata->getRouteIdentifierName());
        $halCollection->setEntityIdentifierName($metadata->getEntityIdentifierName());

        $links = $halCollection->getLinks();
        $this->marshalMetadataLinks($metadata, $links);

        $forceSelfLink = $metadata->getForceSelfLink();
        if ($forceSelfLink && ! $links->has('self')
            && ($metadata->hasUrl() || $metadata->hasRoute())
        ) {
            $link = $this->marshalLinkFromMetadata($metadata, $object);
            $links->add($link);
        }

        return $halCollection;
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
    public function marshalLinkFromMetadata(
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

        if (! $metadata->hasRoute()) {
            throw new Exception\RuntimeException(sprintf(
                'Unable to create a self link for resource of type "%s"; metadata does not contain a route or a url',
                get_class($object)
            ));
        }

        $params = $metadata->getRouteParams();

        // process any callbacks
        foreach ($params as $key => $param) {
            // bind to the object
            if ($param instanceof Closure) {
                $param = $param->bindTo($object);
            }

            // pass the object for callbacks
            if (is_callable($param)) {
                $params[$key] = call_user_func_array($param, [$object]);
            }
        }

        if ($routeIdentifierName) {
            $params = array_merge($params, [$routeIdentifierName => $id]);
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
    public function marshalMetadataLinks(Metadata $metadata, LinkCollection $links)
    {
        foreach ($metadata->getLinks() as $linkData) {
            $link = Link::factory($linkData);
            $links->add($link);
        }
    }
}
