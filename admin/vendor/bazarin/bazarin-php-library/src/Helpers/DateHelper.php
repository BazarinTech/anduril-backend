<?php

namespace Bazarin\Helpers;

class DateHelper {
    // Get today's date
    public static function today($format = 'Y-m-d') {
        return date($format);
    }

    // Format a given date
    public static function format($date, $format = 'Y-m-d') {
        return (new \DateTime($date))->format($format);
    }

    // Get current timestamp
    public static function now($format = 'Y-m-d H:i:s') {
        return date($format);
    }

    // Add days to a given date
    public static function addDays($date, $days, $format = 'Y-m-d') {
        return (new \DateTime($date))->modify("+$days days")->format($format);
    }

    // Subtract days from a given date
    public static function subtractDays($date, $days, $format = 'Y-m-d') {
        return (new \DateTime($date))->modify("-$days days")->format($format);
    }

    // Get difference in days between two dates
    public static function diffInDays($date1, $date2) {
        return (new \DateTime($date1))->diff(new \DateTime($date2))->days;
    }

    // Check if a date is in the past
    public static function isPast($date) {
        return (new \DateTime($date)) < (new \DateTime());
    }

    // Check if a date is in the future
    public static function isFuture($date) {
        return (new \DateTime($date)) > (new \DateTime());
    }

    // Get current timestamp in Unix format
    public static function timestamp() {
        return time();
    }

    // Convert a timestamp to a formatted date
    public static function fromTimestamp($timestamp, $format = 'Y-m-d H:i:s') {
        return date($format, $timestamp);
    }
}

// Example usage
// echo DateHelper::today();
// echo DateHelper::addDays('2025-03-25', 5);
