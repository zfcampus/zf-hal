<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZF\Hal\View;

use ZF\Hal\Collection;
use ZF\Hal\Resource;
use Zend\View\Model\JsonModel;

/**
 * Simple extension to facilitate the specialized JsonStrategy and JsonRenderer
 * in this Module.
 */
class HalJsonModel extends JsonModel
{
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
     * @return bool
     */
    public function isResource()
    {
        $payload = $this->getPayload();
        return ($payload instanceof Resource);
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
}
