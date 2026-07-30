<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class Citizenship extends Model
{
    /** @use HasFactory<\Database\Factories\CitizenshipFactory> */
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
     * Get the user details that reference this citizenship (has-many UserDetail via citizenship_id).
     */
    public function userDetails()
    {
        return $this->hasMany(UserDetail::class, 'citizenship_id', 'id');
    }
}
