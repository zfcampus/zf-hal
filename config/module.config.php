<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZF\Hal;

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
            Extractor\LinkExtractor::class => Factory\LinkExtractorFactory::class,
            Extractor\LinkCollectionExtractor::class => Factory\LinkCollectionExtractorFactory::class,
            HalConfig::class           => Factory\HalConfigFactory::class,
            JsonRenderer::class        => Factory\HalJsonRendererFactory::class,
            JsonStrategy::class        => Factory\HalJsonStrategyFactory::class,
            Link\LinkUrlBuilder::class => Factory\LinkUrlBuilderFactory::class,
            MetadataMap::class         => Factory\MetadataMapFactory::class,
            RendererOptions::class     => Factory\RendererOptionsFactory::class,
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'Hal' => Factory\HalViewHelperFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'Hal' => Factory\HalControllerPluginFactory::class,
        ],
    ],
];
