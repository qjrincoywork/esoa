<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CommonHelper
{
    /**
     * Best-effort fix for legacy mojibake like:
     * - "PIÃ‘AS" or "PIÃƒâ€˜AS" -> "PIÑAS"
     *
     * NOTE: This is a heuristic. The real fix is to store text as proper UTF-8.
     */
    public static function convertStringEncoding($string)
    {
        if ($string === null) {
            return null;
        }

        // Case 1: String is valid UTF-8 but clearly mojibake (Ã… sequences, etc.)
        if (mb_check_encoding($string, 'UTF-8') && str_contains($string, 'Ã')) {
            // Common repair: interpret current UTF-8 string as ISO-8859-1 and
            // re-encode to UTF-8 once. This often turns "PIÃ‘AS" into "PIÑAS".
            $fixed = utf8_encode(utf8_decode($string));

            // Fix very common ñ/Ñ mojibake variants explicitly
            $fixed = strtr($fixed, [
                'Ã‘' => 'Ñ',
                'Ã±' => 'ñ',
                'Ã?' => 'Ñ',
            ]);

            return $fixed;
        }

        // Case 2: Not valid UTF-8 at all; try Latin1 -> UTF-8 conversion once.
        if (!mb_check_encoding($string, 'UTF-8')) {
            $fixed = @mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');

            if (mb_check_encoding($fixed, 'UTF-8')) {
                $fixed = strtr($fixed, [
                    'Ã‘' => 'Ñ',
                    'Ã±' => 'ñ',
                    'Ã?' => 'Ñ',
                ]);

                return $fixed;
            }

            return $string;
        }

        // Already valid and not obviously mojibake; return as-is.
        return $string;
    }

    /**
     * Format a date string according to the given parameters.
     *
     * @param  string|null  $date
     * @param  bool  $withTime
     * @return  string|null
     */
    public static function formatDate($date, $withTime = false)
    {
        if (!$date) {
            return null;
        }

        $date = Carbon::parse($date);

        return $withTime
            ? $date->format('F j, Y')
            : $date->format('F j, Y h:i A');
    }
}
