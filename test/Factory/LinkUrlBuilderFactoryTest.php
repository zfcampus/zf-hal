<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\ServiceManager\ServiceManager;
use ZF\Hal\Factory\LinkUrlBuilderFactory;

class LinkUrlBuilderFactoryTest extends TestCase
{
    public function testInstantiatesLinkUrlBuilder()
    {
        $serviceManager = $this->getServiceManager();

        $factory = new LinkUrlBuilderFactory();
        $builder = $factory($serviceManager);

        $this->assertInstanceOf('ZF\Hal\Link\LinkUrlBuilder', $builder);
    }

    public function testOptionUseProxyIfPresentInConfig()
    {
        $options = [
            'options' => [
                'use_proxy' => true,
            ],
        ];
        $serviceManager = $this->getServiceManager($options);

        $viewHelperManager = $serviceManager->get('ViewHelperManager');
        $serverUrlHelper = $viewHelperManager->get('ServerUrl');

        $serverUrlHelper
            ->expects($this->once())
            ->method('setUseProxy')
            ->with($options['options']['use_proxy']);

        $factory = new LinkUrlBuilderFactory();
        $factory($serviceManager);
    }

    private function getServiceManager($config = [])
    {
        $serviceManager = new ServiceManager();

        $serviceManager->setService('ZF\Hal\HalConfig', $config);

        $viewHelperManager = new ServiceManager();
        $serviceManager->setService('ViewHelperManager', $viewHelperManager);

        $serverUrlHelper = $this->getMock('Zend\View\Helper\ServerUrl');
        $viewHelperManager->setService('ServerUrl', $serverUrlHelper);

        $urlHelper = $this->getMock('Zend\View\Helper\Url');
        $viewHelperManager->setService('Url', $urlHelper);

        return $serviceManager;
    }
}
