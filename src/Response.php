<?php
namespace BiwengerProManagerAPI;

class Response
{
    public static function json($data, $status = 200)
    {
        // Avoid calling header() when headers are already sent (e.g. running under PHPUnit output)
        if (!headers_sent()) {
            header('Content-Type: application/json', true, $status);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public static function error($message, $status = 500)
    {
        self::json(['status' => $status, 'message' => $message, 'data' => null], $status);
    }
}
