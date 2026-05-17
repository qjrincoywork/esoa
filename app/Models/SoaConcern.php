<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SoaConcern extends Model
{
    /** @use HasFactory<\Database\Factories\SoaConcernFactory> */
    use HasFactory;

    protected $fillable = [
        'soa_id',
        'concern_id',
    ];
}
