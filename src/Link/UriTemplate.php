<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal\Link;


class UriTemplate
{
    /** @var array */
    private $queryParameters = [];

    /** @var array */
    private $pathSegmentParameters = [];

    protected function __construct()
    {
    }

    /**
     * @param array $queryParameters
     * @return UriTemplate
     */
    public static function withQueryParameters(array $queryParameters)
    {
        $template = new self();
        $template->queryParameters = $queryParameters;

        return $template;
    }

    /**
     * @param array $pathSegmentParameters
     * @return UriTemplate
     */
    public static function withPathSegmentParameters(array $pathSegmentParameters)
    {
        $template = new self();
        $template->pathSegmentParameters = $pathSegmentParameters;

        return $template;
    }

    /**
     * @return string
     */
    public function getFormattedString()
    {
        if (!empty($this->queryParameters)) {
            return '{?' . implode(',', $this->queryParameters) . '}';
        }
        if (!empty($this->pathSegmentParameters)) {
            return '/{' . implode('}/{', $this->pathSegmentParameters) . '}';
        }
        return '';
    }

}