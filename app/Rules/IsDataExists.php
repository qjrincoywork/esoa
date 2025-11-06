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
     * Create a new rule instance.
     *
     * @param string $table
     */
    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Get all tables in the database main DB
        $tableNames = DB::select('SHOW TABLES');
        $tables = array_map('current', $tableNames);

        if (in_array($this->table, $tables)) {
            // Check if the value exists in the specified table
            $exists = DB::table($this->table)->where('id', $value)->exists();

            if (!$exists) {
                $fail("The {$attribute} is invalid.");
            }
        }
    }
}
