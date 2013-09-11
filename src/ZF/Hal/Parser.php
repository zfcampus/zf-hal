<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZF\Hal;

use IteratorAggregate;
use OutOfRangeException;
use stdClass;

class Parser implements IteratorAggregate
{
    protected $uri;

    protected $links;

    protected $data;

    public static function fromJson($text)
    {
        $data = json_decode($text, false);
        return static::fromStdclass($data);
    }

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
                $resource->{$relation} = static::fromStdclass($embedData);
                continue;
            }
            if (is_array($embedData)) {
                foreach ($embedData as $index => $embedResource) {
                    $embedData[] = static::fromStdclass($embedResource);
                }
                $resource->{$relation} = $embedData;
                continue;
            }

            // @todo trigger an error indicating an invalid value was present
        }

        return new static((array) $resource, $links);
    }

    public function __construct(array $data = array(), Link\LinkCollection $links = null)
    {
        if (null === $links) {
            $links = new Link\LinkCollection();
        }
        $this->data  = $data;
        $this->links = $links;
    }

    public function __get($name)
    {
        if (!array_key_exists($name, $this->data)) {
            throw new OutOfRangeException(sprintf(
                'The value "%s" does not exist in the resource',
                $name
            ));
        }
        return $this->data[$name];
    }

    public function getIterator()
    {
        return ArrayIterator($this->data);
    }

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
