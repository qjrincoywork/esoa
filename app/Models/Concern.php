<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\BelongsTo};

class Concern extends Model
{
    /** @use HasFactory<\Database\Factories\ConcernFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'billing_invoice',
        'type',
        'title',
        'description',
        'status',
        'attachment',
    ];

    /**
     * Method: user
     * This method defines the relationship between the User model and the UserDetail model.
     *
     * @return BelongsTo The relationship between User and UserDetail models.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Method: soa
     * This method defines the relationship between the User model and SoaActivity Model.
     *
     * @return BelongsTo The relationship between User and SoaActivity Model.
     */
    public function soa(): BelongsTo
    {
        return $this->belongsTo(Soa::class, 'billing_invoice', 'soa_number');
    }
}
