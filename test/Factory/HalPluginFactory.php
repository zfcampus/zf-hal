<?php
namespace ZF\Hal\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use ZF\Hal\Plugin\Hal;
class HalPluginFactory implements FactoryInterface
{
    /**
     * @param  ServiceLocatorInterface $serviceLocator
     * @return Metadata\MetadataMap
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $metadataMap     = $serviceLocator->get('ZF\Hal\MetadataMap');
        $hydrators       = $metadataMap->getHydratorManager();
        return new Hal($hydrators);
    }
}