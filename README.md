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

This module utilizes the top level key `zf-hal` for user configuration

#### Key: `renderer`

This is a configuration array is used to configure the `zf-hal` `Hal` service plugin.  It
consists of the following keys:

- `default_hydrator` - if this value is present, this hydrator will be used as the default
  hydrator from the Hal plugin.
- `render_embedded_entities` - boolean, default is true, to embed entities in response
- `render_collections` - boolean, default is true, to render collections in HAL responses
- `hydrators` - a class to service name map of hydrators that HAL can use when hydrating

#### Key: `metadata_map`

- `entity_identifier_name` - name of property used for the id
- `route_name` - a back-reference to the router route name for this resource
- `route_identifier_name` - the identifier name in the route to be mapped to the resource id
- `hydrator` - the hydrator service to use for hydrating this resource
- `is_collection` - boolean; set to true for collections
- `links` - an array of configuration for constructing relational links, structure:
```php
array(
    'rel'   => 'link relation',
    'url'   => 'string absolute URI to use', // OR
    'route' => array(
        'name'    => 'route name for this link',
        'params'  => array( /* any route params to use for link generation */ ),
        'options' => array( /* any options to pass to the router */ ),
    ),
),
// repeat as needed for any additional relational links you want for this resource
```
- `resource_route_name` - route name for embedded resources of a collection
- `route_params` - an array of route params to use for link generation
- `route_options` - an array of options to pass to the router
- `url` - pecific URL to use with this resource, if not using a route

### System Configuration

The following configuration is present to ensure the proper functioning of this module in
a ZF2 based application.

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

This listener is attached to `MvcEvent::EVENT_RENDER` at priority `100`.  If the controller service
result is a `HalJsonModel`, this listener attaches the `ZF\Hal\JsonStrategy` to the view at
priority 200.

ZF2 Services
============

### Models

#### `ZF\Hal\Collection`

`Collection` is responsible for modeling general collections as HAL collections.

#### `ZF\Hal\Entity`

`Entity` is responsible for modeling general purpose entities and plain objects as HAL entities.

#### `ZF\Hal\Link\Link`

`Link\Link` is responsible for modeling a link in HAL.  The `Link\Link` class also has a
`factory()` method that can take an array of information as an argument to produce valid
`Link\Link` instances.

#### `ZF\Hal\Link\LinkCollection`

`LinkCollection` is a model responsible for aggregating a collection of `Link\Link`.

#### `ZF\Hal\Metadata\Metadata`

`Metadata\Metadata` is responsible for collecting all the necessary dependencies, hydrators
and other information necessary to create HAL entities, links, or collections.

#### `ZF\Hal\Metadata\MetadataMap`

`Metadata\MetadataMap` aggregates an array of service/class name keyed `Metadata\Metadata`
instances to be used in producing HAL entities, links, or collections.

### Controller Plugins

#### `ZF\Hal\Plugin\Hal` (a.k.a. `Hal`)

This controller plugin is responsible for providing controllers the facilities to generate
HAL data models based on both configuration and through calling the various factory methods.

### View Layer

#### `ZF\Hal\View\HalJsonModel`

`HalJsonModel` is a view model that when used as the result of a controller service response
signifies to the `zf-hal` module that the data within the model should be utilized to
produce a HAL based HTTP JSON response.

#### `ZF\Hal\View\HalJsonRenderer`

`HalJsonRenderer` is a view renderer responsible for rendering `HalJsonModel`'s.  In turn,
this renderer will call upon the `Plugin\Hal` in order to do the heavy lifting of transforming
the individual model content (`Entity`, `Collection`) into HAL payloads.

#### `ZF\Hal\View\HalJsonStrategy`

`HalJsonStrategy` is responsible for selecting `HalJsonRenderer` when inspecting the controller
service response and it is found to be (or match) `HalJsonModel`.