<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZF\Hal\Exception;

/**
 * Interface for exceptions that can provide additional API Problem details
 */
interface ProblemExceptionInterface
{
    public function getAdditionalDetails();
    public function getDescribedBy();
    public function getTitle();
}
