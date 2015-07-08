<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Extractor;

use JsonSerializable;
use SplObjectStorage;
use Zend\Stdlib\Extractor\ExtractionInterface;
use ZF\Hal\EntityHydratorManager;

class EntityExtractor implements ExtractionInterface
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
     * {@inheritDoc}
     */
    public function extract($entity)
    {
        if (isset($this->serializedEntities[$entity])) {
            return $this->serializedEntities[$entity];
        }

        $array    = false;
        $hydrator = $this->entityHydratorManager->getHydratorForEntity($entity);

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
}
