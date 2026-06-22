<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoaOfficialReceipt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'soa_id',
        'official_receipt_id',
    ];

    public function soa(): BelongsTo
    {
        return $this->belongsTo(Soa::class, 'soa_id');
    }

    public function officialReceipt(): BelongsTo
    {
        return $this->belongsTo(OfficialReceipt::class, 'official_receipt_id');
    }
}
