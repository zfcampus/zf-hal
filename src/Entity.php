<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal;

class Entity implements Link\LinkCollectionAwareInterface
{
    protected $id;

    /**
     * @var Link\LinkCollection
     */
    protected $links;

    protected $entity;

    /**
     * @param  object|array $entity
     * @param  mixed $id
     * @throws Exception\InvalidEntityException if entity is not an object or array
     */
    public function __construct($entity, $id = null)
    {
        if (!is_object($entity) && !is_array($entity)) {
            throw new Exception\InvalidEntityException();
        }

        $this->entity      = $entity;
        $this->id          = $id;
    }

    /**
     * Retrieve properties
     *
     * @param  string $name
     * @throws Exception\InvalidArgumentException
     * @return mixed
     */
    public function &__get($name)
    {
        $names = array(
            'entity' => 'entity',
            'id'     => 'id',
        );
        $name = strtolower($name);
        if (!in_array($name, array_keys($names))) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid property name "%s"',
                $name
            ));
        }
        $prop = $names[$name];
        return $this->{$prop};
    }

    /**
     * Set link collection
     *
     * @param  Link\LinkCollection $links
     * @return self
     */
    public function setLinks(Link\LinkCollection $links)
    {
        $this->links = $links;
        return $this;
    }

    /**
     * Get link collection
     *
     * @return Link\LinkCollection
     */
    public function getLinks()
    {
        if (!$this->links instanceof Link\LinkCollection) {
            $this->setLinks(new Link\LinkCollection());
        }
        return $this->links;
    }
}
