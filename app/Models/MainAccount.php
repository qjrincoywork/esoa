<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class MainAccount extends Model
{
    /** @use HasFactory<\Database\Factories\MainAccountFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'contact_id',
        'code',
        'name',
        'sob',
        'remarks',
        'address',
    ];
}
