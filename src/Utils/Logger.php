<?php

namespace BiwengerProManagerAPI\Utils;

use BiwengerProManagerAPI\Config\Config;

class Logger
{
    private static function writeLog(string $level, $message): void
    {
        $ts = date('Y-m-d H:i:s');
        $line = sprintf("[%s] [%s] %s\n", $ts, strtoupper($level), (string)$message);

        // Resolve configured path. May be a directory or a file (if ends with .log).
        $cfgPath = Config::get('log.path');
        $isFile = false;
        if (is_string($cfgPath) && preg_match('/\.log$/i', $cfgPath)) $isFile = true;

        if (!$cfgPath || !is_string($cfgPath)) {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'biwenger_logs';
            $isFile = false;
        } else {
            $dir = $cfgPath;
        }

        try {
            if ($isFile) {
                $filePath = $dir; // cfgPath is a full file path
                $folder = dirname($filePath);
                if (!is_dir($folder)) @mkdir($folder, 0755, true);
            } else {
                // ensure directory
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $filePath = rtrim($dir, "\\/") . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
            }

            // Append with exclusive lock
            @file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Fallback to PHP error_log if file write fails
            error_log(sprintf('[%s] [%s] %s', $ts, strtoupper($level), (string)$message));
        }
    }

    public static function info($message)
    {
        self::writeLog('INFO', $message);
    }

    public static function error($message)
    {
        self::writeLog('ERROR', $message);
    }
}
