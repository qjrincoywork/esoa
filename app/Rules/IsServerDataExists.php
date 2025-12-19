<?php

namespace App\Rules;

use Closure;
use App\Helpers\SqlDatabase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\{ DB, Schema };

class IsServerDataExists implements ValidationRule
{
    /**
     * The name of the server to check.
     *
     * @var string
     */
    protected $server;

    /**
     * The name of the table to check.
     *
     * @var string
     */
    protected $table;

    /**
     * The name of the column to check.
     *
     * @var string
     */
    protected $column;

    /**
     * Create a new rule instance.
     *
     * @param string $server, string $table, string $column
     */
    public function __construct(string $server, string $table, string $column = null)
    {
        $this->server = $server;
        $this->table = $table;
        $this->column = $column;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $connection = DB::connection($this->server);
        $column = $this->column ?? $attribute;
        // Check if the value exists in the specified table
        $exists = $connection->table($this->table)->where($column, $value)->exists();

        if (!$exists) {
            $fail("The {$column} is invalid.");
        }
    }
}
