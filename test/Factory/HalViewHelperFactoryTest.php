<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\ServiceManager;
use Zend\Hydrator\HydratorPluginManager;
use ZF\Hal\Extractor\LinkCollectionExtractor;
use ZF\Hal\Link;
use ZF\Hal\Factory\HalViewHelperFactory;
use ZF\Hal\Plugin\Hal;
use ZF\Hal\RendererOptions;

class HalViewHelperFactoryTest extends TestCase
{
    /**
     * @var AbstractPluginManager
     */
    private $pluginManager;
    /**
     * @var ServiceManager
     */
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
        $services->setService(RendererOptions::class, $rendererOptions);

        $metadataMap = $this->getMockBuilder('ZF\Hal\Metadata\MetadataMap')->getMock();
        $metadataMap
            ->expects($this->once())
            ->method('getHydratorManager')
            ->will($this->returnValue(new HydratorPluginManager($services)));
        $services->setService('ZF\Hal\MetadataMap', $metadataMap);

        $linkUrlBuilder = $this->getMockBuilder(Link\LinkUrlBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $services->setService(Link\LinkUrlBuilder::class, $linkUrlBuilder);

        $linkCollectionExtractor = $this->getMockBuilder(LinkCollectionExtractor::class)
            ->disableOriginalConstructor()
            ->getMock();
        $services->setService(LinkCollectionExtractor::class, $linkCollectionExtractor);

        $pluginManagerMock = $this->getMockBuilder(AbstractPluginManager::class);
        $pluginManagerMock->setConstructorArgs([$services]);
        $this->pluginManager = $pluginManagerMock->getMock();
        $services->setService('ViewHelperManager', $this->pluginManager);

        $this->services = $services;
    }

    public function testInstantiatesHalViewHelper()
    {
        $this->setupPluginManager();

        $sharedEventManager = $this->getMockBuilder(SharedEventManagerInterface::class)
            ->getMock();
        $eventManagerMock = $this->getMockBuilder(EventManagerInterface::class)
            ->getMock();
        $eventManagerMock->method('getSharedManager')->willReturn($sharedEventManager);

        $this->services->setService('EventManager', $eventManagerMock);

        $factory = new HalViewHelperFactory();
        /** @var Hal $plugin */
        $plugin = $factory($this->services, 'Hal');

        $this->assertInstanceOf('ZF\Hal\Plugin\Hal', $plugin);
        $this->assertInstanceOf(SharedEventManagerInterface::class, $plugin->getEventManager()->getSharedManager());
    }
}
