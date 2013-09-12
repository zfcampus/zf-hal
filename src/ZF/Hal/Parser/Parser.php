<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZF\Hal;

use stdClass;
use ZF\Hal\Exception;
use ZF\Hal\Link;

/**
 * Class for parsing HAL structures into an object graph of Resources
 */
class Parser
{
    /**
     * Parse JSON into resources
     * 
     * @param  mixed $text 
     * @return Resource
     */
    public static function fromJson($text)
    {
        $data = json_decode($text, false);
        return static::fromStdclass($data);
    }

    /**
     * Parse stdClass objects into resources
     * 
     * @param  stdClass $resource 
     * @return Resource
     */
    public static function fromStdclass(stdClass $resource)
    {
        if (!isset($resource->_links)) {
            throw new Exception\InvalidArgumentException(
                'Invalid resource; no _links property'
            );
        }

        $links = $resource->_links;
        unset($resource->_links);
        $links = static::createLinksFromData((array) $links);

        $embedded = array();
        if (isset($resource->_embedded)) {
            $embedded = (array) $resource->_embedded;
            unset($resource->_embedded);
        }

        foreach ($embedded as $relation => $embedData) {
            if (is_object($embedData)) {
                $embedded[$relation] = static::fromStdclass($embedData);
                continue;
            }
            if (is_array($embedData)) {
                foreach ($embedData as $index => $embedResource) {
                    $embedData[$index] = static::fromStdclass($embedResource);
                }
                $embedded[$relation] = $embedData;
                continue;
            }

            // @todo trigger an error indicating an invalid value was present
        }

        return new Resource((array) $resource, $links, $embedded);
    }

    /**
     * Create a Lin\LinkCollection from links in a resource being parsed
     * 
     * @param  array $linkData 
     * @return Link\LinkCollection
     */
    protected static function createLinksFromData(array $linkData)
    {
        $links = new Link\LinkCollection();
        foreach ($linkData as $relation => $linkInfo) {
            if (is_object($linkInfo)) {
                $linkInfo = (array) $linkInfo;
                $linkInfo['rel'] = $relation;
                $linkInfo['url'] = $linkInfo['href'];
                unset($linkInfo['href']);
                $links->add(Link\Link::factory($linkInfo));
                continue;
            }
            if (is_array($linkInfo)) {
                foreach ($linkInfo as $link) {
                    $link = (array) $link;
                    $link['rel'] = $relation;
                    $link['url'] = $link['href'];
                    unset($link['href']);
                    $links->add(Link\Link::factory($link));
                }
                continue;
            }

            // @todo Raise an error if we get an invalid type for a relational link?
        }

        return $links;
    }
}
