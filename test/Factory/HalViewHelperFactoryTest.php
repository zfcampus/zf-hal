<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2017 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit\Framework\TestCase;
use Zend\Hydrator\HydratorPluginManager;
use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\ServiceManager;
use ZF\Hal\Extractor\LinkCollectionExtractor;
use ZF\Hal\Factory\HalViewHelperFactory;
use ZF\Hal\Link;
use ZF\Hal\Metadata\MetadataMap;
use ZF\Hal\Plugin\Hal as HalPlugin;
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
        $services->setService(RendererOptions::class, $rendererOptions);

        $metadataMap = $this->prophesize(MetadataMap::class);
        $metadataMap->getHydratorManager()->willReturn(new HydratorPluginManager($services))->shouldBeCalledTimes(1);
        $services->setService('ZF\Hal\MetadataMap', $metadataMap->reveal());

        $linkUrlBuilder = $this->createMock(Link\LinkUrlBuilder::class);
        $services->setService(Link\LinkUrlBuilder::class, $linkUrlBuilder);

        $linkCollectionExtractor = $this->createMock(LinkCollectionExtractor::class);
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

        $factory = new HalViewHelperFactory();
        $plugin = $factory($this->services);

        $this->assertInstanceOf(HalPlugin::class, $plugin);
    }
}
