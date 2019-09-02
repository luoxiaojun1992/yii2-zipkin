<?php

namespace Lxj\Yii2\Zipkin;

use yii\base\ActionFilter;

/**
 * Class Filter
 * @package Lxj\Yii2\Zipkin
 */
class Filter extends ActionFilter
{
    use Middleware;

    public function init()
    {
        $this->trace();
        parent::init();
    }
}
