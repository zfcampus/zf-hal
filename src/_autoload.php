<?php
/**
 * @see       https://github.com/zfcampus/zf-hal for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zfcampus/zf-hal/blob/master/LICENSE.md New BSD License
 */

namespace ZF\Hal;

use Zend\Hydrator\HydratorPluginManagerInterface;

/**
 * Alias ZF\Hal\Extractor\EntityExtractor to the appropriate class based on
 * which version of zend-hydrator we detect. HydratorPluginManagerInterface
 * is added in v3.
 */
if (interface_exists(HydratorPluginManagerInterface::class, true)) {
    class_alias(Extractor\EntityExtractorHydratorV3::class, Extractor\EntityExtractor::class, true);
} else {
    class_alias(Extractor\EntityExtractorHydratorV2::class, Extractor\EntityExtractor::class, true);
}
