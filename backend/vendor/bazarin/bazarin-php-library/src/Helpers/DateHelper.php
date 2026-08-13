<?php 

namespace Bazarin\Helpers;

class DateHelper {
    public static function format($date, $format = 'Y-m-d') {
        return (new \DateTime($date))->format($format);
    }
}