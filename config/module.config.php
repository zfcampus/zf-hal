<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

return array(
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
        'options' => array(
            // Needed for generate valid _link url when you use a proxy
            'use_proxy' => false,
        ),
    ),
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
    'service_manager' => array(
        'factories' => array(
            'ZF\Hal\JsonRenderer' => 'ZF\Hal\Factory\HalJsonRendererFactory',
            'ZF\Hal\JsonStrategy' => 'ZF\Hal\Factory\HalJsonStrategyFactory',
            'ZF\Hal\MetadataMap'  => 'ZF\Hal\Factory\MetadataMapFactory',
        ),
    ),
    'view_helpers' => array(
        'factories' => array(
            'Hal' => 'ZF\Hal\Factory\HalViewHelperFactory',
        ),
    ),
    'controller_plugins' => array(
        'factories' => array(
            'Hal' => 'ZF\Hal\Factory\HalControllerPluginFactory',
        ),
    ),
);
