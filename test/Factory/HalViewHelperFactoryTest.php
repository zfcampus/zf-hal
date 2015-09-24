<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Hal\Factory;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\ServiceManager\ServiceManager;
use Zend\Hydrator\HydratorPluginManager;
use ZF\Hal\Factory\HalViewHelperFactory;
use ZF\Hal\RendererOptions;

class HalViewHelperFactoryTest extends TestCase
{
    public function testInstantiatesHalViewHelper()
    {
        $pluginManager = $this->getPluginManager();

        $factory = new HalViewHelperFactory();
        $plugin = $factory->createService($pluginManager);

        $this->assertInstanceOf('ZF\Hal\Plugin\Hal', $plugin);
    }

    private function getPluginManager()
    {
        $services = new ServiceManager();

        $services->setService('ZF\Hal\HalConfig', []);
        $services->setService('ZF\Hal\RendererOptions', new RendererOptions());

        $metadataMap = $this->getMock('ZF\Hal\Metadata\MetadataMap');
        $metadataMap
            ->expects($this->once())
            ->method('getHydratorManager')
            ->will($this->returnValue(new HydratorPluginManager()));

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

        return $pluginManager;
    }
}
