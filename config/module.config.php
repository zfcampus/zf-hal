<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

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
            'ZF\Hal\HalConfig'       => 'ZF\Hal\Factory\HalConfigFactory',
            'ZF\Hal\JsonRenderer'    => 'ZF\Hal\Factory\HalJsonRendererFactory',
            'ZF\Hal\JsonStrategy'    => 'ZF\Hal\Factory\HalJsonStrategyFactory',
            'ZF\Hal\MetadataMap'     => 'ZF\Hal\Factory\MetadataMapFactory',
            'ZF\Hal\RendererOptions' => 'ZF\Hal\Factory\RendererOptionsFactory',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'Hal' => 'ZF\Hal\Factory\HalViewHelperFactory',
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'Hal' => 'ZF\Hal\Factory\HalControllerPluginFactory',
        ],
    ],
];
