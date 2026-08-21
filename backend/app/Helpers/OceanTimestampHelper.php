<?php

namespace App\Helpers;

use Carbon\Carbon;

/**
 * Helper functions for Ocean Express timestamp handling.
 */
class OceanTimestampHelper
{
    /**
     * Convert Ocean Express timestamps (ISO-8601 UTC, plain UTC string, or epoch-ms) to the configured shop timezone.
     *
     * @param  array  $payload  Webhook data or API response from Ocean Express
     */
    public static function parseOceanTimestamp(array $payload): Carbon
    {
        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');
        $raw = $payload['timestamp'] ?? $payload['timestamp_epoch'] ?? $payload['created_at'] ?? $payload['happened_at'] ?? null;

        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return Carbon::now($tz);
        }

        // 1. Epoch timestamps (numeric)
        if (is_numeric($raw)) {
            $val = (int) $raw;
            // Greater than 10 billion means milliseconds (e.g. 1787285537000)
            if ($val > 10000000000) {
                return Carbon::createFromTimestampMs($val)->setTimezone($tz);
            }

            return Carbon::createFromTimestamp($val, $tz);
        }

        // 2. String formats
        if (is_string($raw)) {
            $timeStr = trim($raw);
            try {
                // ISO with Z or timezone offset (+07:00 / +00:00)
                if (str_ends_with($timeStr, 'Z') || preg_match('/[+-]\d{2}:\d{2}$/', $timeStr)) {
                    return Carbon::parse($timeStr)->setTimezone($tz);
                }

                // Plain datetime string without offset sent by Ocean Express API/DB (UTC)
                if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $timeStr)) {
                    return Carbon::parse($timeStr, 'UTC')->setTimezone($tz);
                }

                // General fallback parsing
                return Carbon::parse($timeStr)->setTimezone($tz);
            } catch (\Throwable) {
                return Carbon::now($tz);
            }
        }

        return Carbon::now($tz);
    }
}
