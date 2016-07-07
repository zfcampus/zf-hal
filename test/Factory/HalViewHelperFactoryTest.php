<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use ReflectionObject;
use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\ServiceManager;
use Zend\Hydrator\HydratorPluginManager;
use ZF\Hal\Factory\HalViewHelperFactory;
use ZF\Hal\RendererOptions;

class HalViewHelperFactoryTest extends TestCase
{
    private $pluginManager;
    private $services;

    public function setupPluginManager($config = [])
    {
        $services = new ServiceManager();

        $services->setService('ZF\Hal\HalConfig', $config);

        if (isset($config['renderer']) && is_array($config['renderer'])) {
            $rendererOptions = new RendererOptions($config['renderer']);
        } else {
            $rendererOptions = new RendererOptions();
        }

        $metadataMap = $this->createMock('ZF\Hal\Metadata\MetadataMap');
        $metadataMap
            ->expects($this->once())
            ->method('getHydratorManager')
            ->will($this->returnValue(new HydratorPluginManager($services)));

        $services->setService('ZF\Hal\MetadataMap', $metadataMap);

        $linkUrlBuilder = $this->getMockBuilder('ZF\Hal\Link\LinkUrlBuilder')
            ->disableOriginalConstructor()
            ->getMock();
        $services->setService('ZF\Hal\Link\LinkUrlBuilder', $linkUrlBuilder);

        $linkCollectionExtractor = $this->getMockBuilder('ZF\Hal\Extractor\LinkCollectionExtractor')
            ->disableOriginalConstructor()
            ->getMock();
        $services->setService('ZF\Hal\Extractor\LinkCollectionExtractor', $linkCollectionExtractor);

        $pluginManager = $this->getMock('Zend\ServiceManager\AbstractPluginManager');
        $pluginManager
            ->method('getServiceLocator')
            ->will($this->returnValue($services));
    }

    public function testInstantiatesHalViewHelper()
    {
        $this->setupPluginManager();

        $factory = new HalViewHelperFactory();
        $plugin = $factory($this->services, 'Hal');

        $this->assertInstanceOf('ZF\Hal\Plugin\Hal', $plugin);
    }

    /**
     * @group fail
     */
    public function testOptionUseProxyIfPresentInConfig()
    {
        $options = [
            'options' => [
                'use_proxy' => true,
            ],
        ];

        $this->setupPluginManager($options);

        $factory = new HalViewHelperFactory();
        $halPlugin = $factory($this->services, 'Hal');

        $r = new ReflectionObject($halPlugin);
        $p = $r->getProperty('serverUrlHelper');
        $p->setAccessible(true);
        $serverUrlPlugin = $p->getValue($halPlugin);
        $this->assertInstanceOf('Zend\View\Helper\ServerUrl', $serverUrlPlugin);

        return $pluginManager;
    }
}
