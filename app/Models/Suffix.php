<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class Suffix extends Model
{
    /** @use HasFactory<\Database\Factories\SuffixFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the user details that reference this suffix (has-many UserDetail via suffix_id).
     */
    public function userDetails()
    {
        return $this->hasMany(UserDetail::class, 'suffix_id', 'id');
    }
}
