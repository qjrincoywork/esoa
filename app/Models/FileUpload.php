<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\HasMany, Relations\HasOne, Relations\BelongsTo};

class FileUpload extends Model
{
    /** @use HasFactory<\Database\Factories\FileUploadFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'soa_id',
        'file_path',
    ];

    /**
     * Get the user who uploaded this file (belongs-to User via user_id).
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the SOA this file is attached to (belongs-to Soa via soa_id).
     *
     * @return BelongsTo
     */
    public function soa(): BelongsTo
    {
        return $this->belongsTo(Soa::class, 'soa_id');
    }
}
