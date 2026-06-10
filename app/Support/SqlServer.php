<?php

namespace App\Support;

class SqlServer
{
    /**
     * Escape credentials for the Microsoft SQL Server PDO driver.
     *
     * Braces in usernames/passwords must be doubled ({ -> {{, } -> }})
     * or the driver raises SQLSTATE[IMSSP] during connect/query.
     *
     * @see https://learn.microsoft.com/en-us/sql/connect/php/microsoft-php-driver-for-sql-server
     */
    public static function escapeCredential(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return str_replace(['}', '{'], ['}}', '{{'], $value);
    }
}
