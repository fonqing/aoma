<?php
namespace Aoma;
/**
 * A Date helper library
 *
 * @author Eric Wang,<fonqing@gmail.com>
 * @copyright Aomasoft co.,Ltd. 2021
 * @version 1
 */

class DatePlus {

    /**
     * Special work days
     *
     * @var array
     */
    public static $workdays = [];

    /**
     * Holidays list
     *
     * @var array
     */
    public static $holidays = [];

    /**
     * Find whether a day is workday or holiday
     *
     * @param string $date The date
     * @return boolean
     */
    public static function isWorkDay(string $date) : bool
    {
        $time = strtotime($date);
        $date = date('Y-m-d', $time);
        if(in_array($date, self::$workdays)){
            return true;
        }
        if(in_array($date, self::$holidays)){
            return false;
        }
        $week = date('w', $time);
        return ( $week > 0 && $week < 6 );
    }

    /**
     * Find whether a datetime string is valid
     *
     * @param string $string datetime string
     * @param string $format Format
     *
     * @return bool
     */
    public static function isValidDate(string $string, string $format = 'Y-m-d') : bool
    {
        $string = trim($string);
        $format = trim($format);
        $result = date($format, strtotime($string));
        return $string === $result;
    }

    /**
     * Create a month list 
     *
     * @param string $start start year and month
     * @param string $end end year and month
     * @return array
     * 
     * <code lang="php">
     * $list = DatePlus::getMonthList('2021-01', '2021-12');
     * </code>
     */
    public static function getMonthList(string $start, string $end) : array
    {
        $start = strtotime($start . '-01');
        $end   = strtotime($end   . '-01');
        if($start > $end) {
            return [];
        }
        $data   = [];
        $data[] = date('Y-m', $start);
        while( ($start = strtotime('+1 month', $start)) <= $end){
            $data[] = date('Y-m', $start);
        }
        return $data;
    }

    /**
     * Create day list 
     *
     * @param string|integer $start Start date
     * @param string|integer $end End date
     * @param boolean $timestamp if true will return a timestamp in integer
     * @return array
     * <code>
     * $list = DatePlus::getDayList('2021-10-01', '2021-10-03');
     * //Will get array bellow
     * //['2021-10-01', '2021-10-02', '2021-10-03']
     * </code>
     */
    public static function getDayList($start, $end, bool $timestamp = false) : array
    {
        $start = is_int($start) ? $start : strtotime($start);
        $end   = is_int($end)   ? $end   : strtotime($end);
        if($start > $end) {
            return [];
        }
        $data   = [];
        $data[] = $timestamp ? $start : date('Y-m-d', $start);
        while( ($start = strtotime('+1 day', $start)) <= $end){
            $data[] = $timestamp ? $start : date('Y-m-d', $start);
        }
        return $data;
    }

    /**
     * Create time list
     *
     * @param string|integer $start start time
     * @param string|integer $end   end time
     * @param integer $size Duration
     * @param boolean $timestamp 
     * @return array
     * 
     * <code>
     * $list = DatePlus::getTimeList('2021-10-01 8:00:00', '2021-10-01 10:00:00');
     * //Will get array bellow
     * //['8:00:00', '9:00:00', '10:00:00']
     * </code>
     */
    public static function getTimeList($start, $end, $size = 3600, $timestamp = false)
    {
        $time1 = is_int($start) ? $start : strtotime($start);
        $time2 = is_int($end)   ? $end   : strtotime($end);
        if($time1 > $time2){
            return [];
        }
        $data   = [];
        $data[] = $timestamp ? $time1 : date('H:i', $time1);
        while( ($time1 = $time1 + $size) <= $time2){
            $data[] = $timestamp ? $time1 : date('H:i', $time1);
        }
        return $data;
    }

    /**
     * Get weekday Chinese name
     *
     * @param string $dateString
     *
     * @return string
     */
    public static function getWeekName(string $dateString, bool $short = false) : string
    {
        $timestamp = strtotime($dateString);
        if ( $timestamp < 1 ){
            return '';
        }
        $weekNames = [
            ['日', '星期日'],
            ['一', '星期一'],
            ['二', '星期二'],
            ['三', '星期三'],
            ['四', '星期四'],
            ['五', '星期五'],
            ['六', '星期六'],
        ];
        $index = (int) date('w', $timestamp);
        $short = $short ? 0 : 1;
        return $weekNames[$index][$short] ?? '';
    }

    /**
     * Get week start date and week end date by a given date
     *
     * Attention: Start on Monday
     * 
     * @param string|integer $time
     * @return array
     */
    public static function weekRange($time = 0, bool $timestamp = false) : array
    {
        $time = is_int($time) ? $time : strtotime($time);
        if($time < 1){
            $time = time();
        }
        $week = (int) date('N', $time);
        $mon  = $time - ( $week - 1 ) * 86400;
        $sun  = $time + abs( $week - 7 ) * 86400;
        return $timestamp ? [$mon, $sun] : [date('Y-m-d', $mon), date('Y-m-d', $sun)];
    }

}
