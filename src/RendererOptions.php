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
     * @return string
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
     * @return string
     */
    public function getRenderEmbeddedCollections()
    {
        return $this->renderEmbeddedCollections;
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
