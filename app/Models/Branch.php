<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class Branch extends Model
{
    /** @use HasFactory<\Database\Factories\BranchFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'cm_code',
        'ac_code',
        'integration',
        'tin',
        'address',
        'attention',
        'position',
        'disclaimer',
        'created_by', //reference by user_id - users table
        'updated_by', //reference by user_id - users table
    ];
}
