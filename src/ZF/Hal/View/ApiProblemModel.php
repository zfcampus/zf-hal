<?php
/**
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 */

namespace ZF\Hal\View;

use Zend\View\Model\ViewModel;
use ZF\Hal\ApiProblem;

class ApiProblemModel extends ViewModel
{
    protected $captureTo = 'errors';
    protected $problem;
    protected $terminate = false;

    public function __construct($problem = null, $options = null)
    {
        if ($problem instanceof ApiProblem) {
            $this->setApiProblem($problem);
        }
    }

    public function setApiProblem(ApiProblem $problem)
    {
        $this->problem = $problem;
        return $this;
    }

    public function getApiProblem()
    {
        return $this->problem;
    }
}
