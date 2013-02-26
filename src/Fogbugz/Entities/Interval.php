<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ollie Maitland
 * Date: 23/02/13
 * Time: 10:44
 * To change this template use File | Settings | File Templates.
 */

namespace Fogbugz\Entities;

class Interval
{
    public $case;
    public $start;
    public $end;
    public $person;

    public function getDurationHours()
    {
        return ((strtotime($this->end) - strtotime($this->start))/3600);
    }
}