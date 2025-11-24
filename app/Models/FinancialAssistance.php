<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes};

class FinancialAssistance extends Model
{
    /** @use HasFactory<\Database\Factories\FinancialAssistanceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fin_code',
        'amount',
        'natural_death_amount',
        'accident_death_amount',
        'dismemberment_amount',
        'remarks',
    ];
}
