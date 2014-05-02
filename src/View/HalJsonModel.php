<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\View;

use ZF\Hal\Collection;
use ZF\Hal\Entity;
use Zend\View\Model\JsonModel;

/**
 * Simple extension to facilitate the specialized JsonStrategy and JsonRenderer
 * in this Module.
 */
class HalJsonModel extends JsonModel
{
    /**
     * @var bool
     */
    protected $terminate = true;

    /**
     * Does the payload represent a HAL collection?
     *
     * @return bool
     */
    public function isCollection()
    {
        $payload = $this->getPayload();
        return ($payload instanceof Collection);
    }

    /**
     * Does the payload represent a HAL item?
     *
     * Deprecated; please use isEntity().
     *
     * @deprecated
     * @return bool
     */
    public function isResource()
    {
        trigger_error(sprintf('%s is deprecated; please use %s::isEntity', __METHOD__, __CLASS__), E_USER_DEPRECATED);
        return self::isEntity();
    }

    /**
     * Does the payload represent a HAL entity?
     *
     * @return bool
     */
    public function isEntity()
    {
        $payload = $this->getPayload();
        return ($payload instanceof Entity);
    }

    /**
     * Set the payload for the response
     *
     * This is the value to represent in the response.
     *
     * @param  mixed $payload
     * @return self
     */
    public function setPayload($payload)
    {
        $this->setVariable('payload', $payload);
        return $this;
    }

    /**
     * Retrieve the payload for the response
     *
     * @return mixed
     */
    public function getPayload()
    {
        return $this->getVariable('payload');
    }

    /**
     * Override setTerminal()
     *
     * Does nothing; does not allow re-setting "terminate" flag.
     *
     * @param  bool $flag
     * @return self
     */
    public function setTerminal($flag = true)
    {
        return $this;
    }
}
