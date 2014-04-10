ZF HAL
======

[![Build Status](https://travis-ci.org/zfcampus/zf-hal.png)](https://travis-ci.org/zfcampus/zf-hal)

Introduction
------------

This module provides data structures for Hypermedia Application Language, as
well as the ability to render them to JSON.

- [HAL](http://tools.ietf.org/html/draft-kelly-json-hal-03), used for creating
  hypermedia links
- [Problem API](http://tools.ietf.org/html/draft-nottingham-http-problem-02),
  used for reporting API problems

Installation
------------

Run the following `composer` command:

```console
$ composer require "zfcampus/zf-hal:~1.0-dev"
```

Alternately, manually add the following to your `composer.json`, in the `require` section:

```javascript
"require": {
    "zfcampus/zf-hal": "~1.0-dev"
}
```

And then run `composer update` to ensure the module is installed.

Finally, add the module name to your project's `config/application.config.php` under the `modules`
key:

```php
return array(
    /* ... */
    'modules' => array(
        /* ... */
        'ZF\Hal',
    ),
    /* ... */
);
```

Configuration
=============

### User Configuration

```php
'zf-hal' => array(
    'renderer' => array(
        // 'default_hydrator' => 'Hydrator Service Name',
        // 'hydrators'        => array(
        //     class to hydrate/hydrator service name pairs
        // ),
    ),
    'metadata_map' => array(
        // 'Class Name' => array(
        //     'hydrator'        => 'Hydrator Service Name, if a resource',
        //     'entity_identifier_name' => 'identifying field name, if a resource',
        //     'route_name'      => 'name of route for this resource',
        //     'is_collection'   => 'boolean; set to true for collections',
        //     'links'           => array(
        //         array(
        //             'rel'   => 'link relation',
        //             'url'   => 'string absolute URI to use', // OR
        //             'route' => array(
        //                 'name'    => 'route name for this link',
        //                 'params'  => array( /* any route params to use for link generation */ ),
        //                 'options' => array( /* any options to pass to the router */ ),
        //             ),
        //         ),
        //         repeat as needed for any additional relational links you want for this resource
        //     ),
        //     'resource_route_name' => 'route name for embedded resources of a collection',
        //     'route_params'        => array( /* any route params to use for link generation */ ),
        //     'route_options'       => array( /* any options to pass to the router */ ),
        //     'url'                 => 'specific URL to use with this resource, if not using a route',
        // ),
        // repeat as needed for each resource/collection type
    ),
),
```

### System Configuration

```php
// Creates a "HalJson" selector for zfcampus/zf-content-negotiation
'zf-content-negotiation' => array(
    'selectors' => array(
        'HalJson' => array(
            'ZF\Hal\View\HalJsonModel' => array(
                'application/json',
                'application/*+json',
            ),
        ),
    ),
),
```

ZF2 Events
==========

### Listeners

#### `ZF\Hal\Module::onRender`



ZF2 Services
============

### Models

#### `ZF\Hal\Collection`

#### `ZF\Hal\Entity`

#### `ZF\Hal\Resource`

#### `ZF\Hal\Link\Link`

#### `ZF\Hal\Link\LinkCollection`

#### `ZF\Hal\Metadata\Metadata`

#### `ZF\Hal\Metadata\MetadataMap`

### Controller Plugins

#### `ZF\Hal\Plugin\Hal` (a.k.a. `Hal`)

### View Layer

#### `ZF\Hal\View\HalJsonModel`

#### `ZF\Hal\View\HalJsonRenderer`

#### `ZF\Hal\View\HalJsonStrategy`
