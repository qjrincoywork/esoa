<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{Model, SoftDeletes, Relations\BelongsTo};

class AccountPaymentActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_payment_id',
        'name',
        'event',
        'from',
        'to',
    ];

    protected $casts = [
        'from' => 'array',
        'to'   => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function accountPayment(): BelongsTo
    {
        return $this->belongsTo(AccountPayment::class, 'account_payment_id');
    }
}
