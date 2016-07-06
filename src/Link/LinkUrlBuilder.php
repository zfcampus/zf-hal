<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Link;

use Zend\View\Helper\ServerUrl;
use Zend\View\Helper\Url;

class LinkUrlBuilder
{
    /**
     * @var ServerUrl
     */
    protected $serverUrlHelper;

    /**
     * @var Url
     */
    protected $urlHelper;

    /**
     * @param ServerUrl $serverUrlHelper
     * @param Url $urlHelper
     */
    public function __construct(ServerUrl $serverUrlHelper, Url $urlHelper)
    {
        $this->serverUrlHelper = $serverUrlHelper;
        $this->urlHelper       = $urlHelper;
    }

    /**
     * @param  string $route
     * @param  array $params
     * @param  array $options
     * @param  bool $reUseMatchedParams
     * @return string
     */
    public function buildLinkUrl($route, $params = [], $options = [], $reUseMatchedParams = false)
    {
        $path = call_user_func(
            $this->urlHelper,
            $route,
            $params,
            $options,
            $reUseMatchedParams
        );

        if (substr($path, 0, 4) == 'http') {
            return $path;
        }

        return call_user_func($this->serverUrlHelper, $path);
    }
}
