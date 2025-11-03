<?php
namespace BiwengerProManagerAPI\Utils;

class Utils
{
    /**
     * Return a week key integer for a given date (year * 100 + ISO week number).
     */
    public static function weekKey(string $date = 'now'): int
    {
        try {
            $dt = new \DateTime($date);
            $week = (int)$dt->format('W');
            $year = (int)$dt->format('o'); // ISO-8601 year
            return $year * 100 + $week;
        } catch (\Exception $e) {
            // Log invalid date formats because callers may pass user input
            if (class_exists('BiwengerProManagerAPI\\Utils\\Logger')) {
                \BiwengerProManagerAPI\Utils\Logger::error('Utils::weekKey invalid date "' . $date . '": ' . $e->getMessage());
            }
            return 0;
        }
    }

    public static function getAsInt($number): int {
        return (is_numeric($number) ? (int)$number : 0);
    }
}
