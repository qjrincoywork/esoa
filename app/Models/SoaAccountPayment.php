<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SoaAccountPayment extends Model
{
    /** @use HasFactory<\Database\Factories\SoaAccountPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'soa_id',
        'account_payment_id',
        'applied_amount',
    ];
}
