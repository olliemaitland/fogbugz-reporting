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
    const DATE_FORMAT = 'Y-m-d';

    public $case;
    public $start;
    public $end;
    public $person;

    public function __construct(\SimpleXMLElement $intervalTag)
    {
        $this->case     = (string) $intervalTag->ixBug;
        $this->start    = (string) $intervalTag->dtStart;
        $this->end      = (string) $intervalTag->dtEnd;
        $this->person   = (string) $intervalTag->ixPerson;
    }

    /**
     * Returns number of hours in the interval
     *
     * @return  float
     */
    public function getDurationHours()
    {
        $start  = new \DateTime($this->start);
        $end    = new \DateTime($this->end);

        $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
        return $hours;
    }

    public function getStartDay()
    {
        $start = new \DateTime($this->start);

        return $start->format(self::DATE_FORMAT);
    }

    /**
     * Returns date of start week Monday
     *
     * @return   string
     */
    public function getStartWeek()
    {
        $start = new \DateTime($this->start);

        if ($start->format('l') !== 'Monday') {
            $start->modify('Monday this week');
        }

        return $start->format(self::DATE_FORMAT);
    }
}