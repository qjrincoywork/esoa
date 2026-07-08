<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\{ DB, Schema };

class IsDataExists implements ValidationRule
{
    /**
     * The name of the table to check.
     *
     * @var string
     */
    protected $table;

    /**
     * Create the rule for the local table the value's id must exist in.
     *
     * @param string $table
     */
    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Pass only when a row with id equal to the value exists in the configured table.
     *
     * The list of real tables is resolved from the current connection (SHOW
     * TABLES on MySQL, INFORMATION_SCHEMA otherwise); the rule fails closed with
     * "The {attribute} is invalid." both when the table is not a known base
     * table and when no matching id is found.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Get all tables in the database main DB
        if (env('DB_CONNECTION') == 'mysql') {
            $tableNames = DB::select('SHOW TABLES');
            $tables = array_map('current', $tableNames);
        } else {
            $tables = collect(DB::select("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_TYPE = 'BASE TABLE'
            "))
            ->pluck('TABLE_NAME')
            ->toArray();
        }

        // Fail closed: an unknown table must never let the value through.
        if (!in_array($this->table, $tables)) {
            $fail("The {$attribute} is invalid.");
            return;
        }

        // Check if the value exists in the specified table
        $exists = DB::table($this->table)->where('id', $value)->exists();

        if (!$exists) {
            $fail("The {$attribute} is invalid.");
        }
    }
}
