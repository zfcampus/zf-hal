<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

use ZF\Hal\Factory\HalConfigFactory;
use ZF\Hal\Factory\HalControllerPluginFactory;
use ZF\Hal\Factory\HalJsonRendererFactory;
use ZF\Hal\Factory\HalJsonStrategyFactory;
use ZF\Hal\Factory\HalViewHelperFactory;
use ZF\Hal\Factory\MetadataMapFactory;
use ZF\Hal\Factory\RendererOptionsFactory;

return [
    'zf-hal' => [
        'renderer' => [
            // 'default_hydrator' => 'Hydrator Service Name',
            // 'hydrators'        => [
            //     class to hydrate/hydrator service name pairs
            // ],
        ],
        'metadata_map' => [
            // 'Class Name' => [
            //     'hydrator'        => 'Hydrator Service Name, if a resource',
            //     'entity_identifier_name' => 'identifying field name, if a resource',
            //     'route_name'      => 'name of route for this resource',
            //     'is_collection'   => 'boolean; set to true for collections',
            //     'links'           => [
            //         [
            //             'rel'   => 'link relation',
            //             'url'   => 'string absolute URI to use', // OR
            //             'route' => [
            //                 'name'    => 'route name for this link',
            //                 'params'  => [ /* any route params to use for link generation */ ],
            //                 'options' => [ /* any options to pass to the router */ ],
            //             ],
            //         ],
            //         repeat as needed for any additional relational links you want for this resource
            //     ],
            //     'resource_route_name' => 'route name for embedded resources of a collection',
            //     'route_params'        => [ /* any route params to use for link generation */ ],
            //     'route_options'       => [ /* any options to pass to the router */ ],
            //     'url'                 => 'specific URL to use with this resource, if not using a route',
            // ],
            // repeat as needed for each resource/collection type
        ],
        'options' => [
            // Needed for generate valid _link url when you use a proxy
            'use_proxy' => false,
        ],
    ],
    // Creates a "HalJson" selector for zfcampus/zf-content-negotiation
    'zf-content-negotiation' => [
        'selectors' => [
            'HalJson' => [
                'ZF\Hal\View\HalJsonModel' => [
                    'application/json',
                    'application/*+json',
                ],
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            'ZF\Hal\HalConfig'       => HalConfigFactory::class,
            'ZF\Hal\JsonRenderer'    => HalJsonRendererFactory::class,
            'ZF\Hal\JsonStrategy'    => HalJsonStrategyFactory::class,
            'ZF\Hal\MetadataMap'     => MetadataMapFactory::class,
            'ZF\Hal\RendererOptions' => RendererOptionsFactory::class,
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'Hal' => HalViewHelperFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'Hal' => HalControllerPluginFactory::class,
        ],
    ],
];
