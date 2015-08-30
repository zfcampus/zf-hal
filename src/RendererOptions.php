<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal;

use Zend\Stdlib\AbstractOptions;

class RendererOptions extends AbstractOptions
{
    /**
     * @var string
     */
    protected $defaultHydrator;

    /**
     * @var bool
     */
    protected $renderEmbeddedEntities = true;

    /**
     * @var bool
     */
    protected $renderEmbeddedCollections = true;

    /**
     * When set to true embedded entities will will be created as _links members instead of inside _embedded.
     * This option is only in effect when @see renderEmbeddedEntities is false. Defaulted to false for non-breaking
     * compatibility concerns
     * @var bool
     */
    protected $useResourceLinksInsteadOfEmbeddedEntityLink = false;

    /**
     * @var array
     */
    protected $hydrators = [];

    /**
     * @param string $hydrator
     */
    public function setDefaultHydrator($hydrator)
    {
        $this->defaultHydrator = $hydrator;
    }

    /**
     * @return string
     */
    public function getDefaultHydrator()
    {
        return $this->defaultHydrator;
    }

    /**
     * @param bool $flag
     */
    public function setRenderEmbeddedEntities($flag)
    {
        $this->renderEmbeddedEntities = (bool) $flag;
    }

    /**
     * @return bool
     */
    public function getRenderEmbeddedEntities()
    {
        return $this->renderEmbeddedEntities;
    }

    /**
     * @param bool $flag
     */
    public function setRenderEmbeddedCollections($flag)
    {
        $this->renderEmbeddedCollections = (bool) $flag;
    }

    /**
     * @return bool
     */
    public function getRenderEmbeddedCollections()
    {
        return $this->renderEmbeddedCollections;
    }

    /**
     * @return bool
     */
    public function getUseResourceLinksInsteadOfEmbeddedEntityLink()
    {
        return $this->useResourceLinksInsteadOfEmbeddedEntityLink;
    }

    /**
     * @param bool $flag
     */
    public function setUseResourceLinksInsteadOfEmbeddedEntityLink($flag)
    {
        $this->useResourceLinksInsteadOfEmbeddedEntityLink = (bool) $flag;
    }

    /**
     * @param array $hydrators
     */
    public function setHydrators(array $hydrators)
    {
        $this->hydrators = $hydrators;
    }

    /**
     * @return string
     */
    public function getHydrators()
    {
        return $this->hydrators;
    }
}
