<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Extractor;

use JsonSerializable;
use SplObjectStorage;
use Zend\Hydrator\ExtractionInterface;
use ZF\Hal\EntityHydratorManager;

/**
 * Extract entities.
 *
 * This version targets zend-hydrator v3, and will be aliased to
 * ZF\Hal\Extractor\EntityExtractor when that versions is in use.
 */
class EntityExtractorHydratorV3 implements ExtractionInterface
{
    /**
     * @var EntityHydratorManager
     */
    protected $entityHydratorManager;

    /**
     * Map of entities to their ZF\Hal\Entity serializations
     *
     * @var SplObjectStorage
     */
    protected $serializedEntities;

    /**
     * @param EntityHydratorManager $entityHydratorManager
     */
    public function __construct(EntityHydratorManager $entityHydratorManager)
    {
        $this->entityHydratorManager = $entityHydratorManager;
        $this->serializedEntities    = new SplObjectStorage();
    }

    /**
     * @inheritDoc
     */
    public function extract(object $entity) : array
    {
        if (isset($this->serializedEntities[$entity])) {
            return $this->serializedEntities[$entity];
        }

        $this->serializedEntities[$entity] = $this->extractEntity($entity);

        return $this->serializedEntities[$entity];
    }

    private function extractEntity(object $entity) : array
    {
        $hydrator = $this->entityHydratorManager->getHydratorForEntity($entity);

        if ($hydrator) {
            return $hydrator->extract($entity);
        }

        if ($entity instanceof JsonSerializable) {
            return $entity->jsonSerialize();
        }

        return get_object_vars($entity);
    }
}
