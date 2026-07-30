<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\HasMany, Relations\HasOne, Relations\BelongsTo};

class SoaActivity extends Model
{
    /** @use HasFactory<\Database\Factories\SoaActivityFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'soa_id',
        'name',
        'event',
        'from',
        'to',
    ];

    protected $casts = [
        'from' => 'array',
        'to' => 'array',
    ];

    /**
     * Get the user who performed this activity (belongs-to User via user_id).
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the SOA this activity was recorded against (belongs-to Soa via soa_id).
     *
     * @return BelongsTo
     */
    public function soa(): BelongsTo
    {
        return $this->belongsTo(Soa::class, 'soa_id');
    }
}
