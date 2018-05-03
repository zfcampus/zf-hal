<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal;

class Entity implements Link\LinkCollectionAwareInterface
{
    use Link\LinkCollectionAwareTrait;

    /**
     * @var object|array
     */
    protected $entity;

    /**
     * @var mixed
     */
    protected $id;

    /**
     * @param  object|array $entity
     * @param  mixed $id
     * @throws Exception\InvalidEntityException if entity is not an object or array
     */
    public function __construct($entity, $id = null)
    {
        if (! is_object($entity) && ! is_array($entity)) {
            throw new Exception\InvalidEntityException();
        }

        $this->entity      = $entity;
        $this->id          = $id;
    }

    /**
     * Retrieve properties
     *
     * @deprecated
     * @param  string $name
     * @throws Exception\InvalidArgumentException
     * @return mixed
     */
    public function &__get($name)
    {
        trigger_error(
            sprintf(
                'Direct property access to %s::$%s is deprecated, use getters instead.',
                __CLASS__,
                $name
            ),
            E_USER_DEPRECATED
        );
        $names = [
            'entity' => 'entity',
            'id'     => 'id',
        ];
        $name = strtolower($name);
        if (! in_array($name, array_keys($names))) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid property name "%s"',
                $name
            ));
        }
        $prop = $names[$name];
        return $this->{$prop};
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * TODO: Get by reference is that really necessary?
     *
     * @return object|array
     */
    public function &getEntity()
    {
        return $this->entity;
    }
}
