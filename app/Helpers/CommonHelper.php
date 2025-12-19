<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class CommonHelper
{
    // public static function getUserIP()
    // {
    //     $ip = request()->ip();
    //     $remote = request()->server('REMOTE_ADDR');
    //     $forward = request()->server('HTTP_X_FORWARDED_FOR');
    //     $client = request()->server('HTTP_CLIENT_IP');

    //     if (filter_var($client, FILTER_VALIDATE_IP)) {
    //         $ip = $client;
    //     } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
    //         $ip = $forward;
    //     } else {
    //         $ip = $remote;
    //     }

    //     return $ip;
    // }
}
