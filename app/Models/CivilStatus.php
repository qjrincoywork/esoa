<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class CivilStatus extends Model
{
    /** @use HasFactory<\Database\Factories\CivilStatusFactory> */
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
     * Get the user details that reference this civil status (has-many UserDetail via civil_status_id).
     */
    public function userDetails()
    {
        return $this->hasMany(UserDetail::class, 'civil_status_id', 'id');
    }
}
