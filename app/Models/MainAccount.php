<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class MainAccount extends Model
{
    /** @use HasFactory<\Database\Factories\PositionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'sob',
        'remarks',
        'address',
        'contact_person',
        'contact_no',
        'franchise_code',
        'integration',
        'prexexpiry',
    ];
}
