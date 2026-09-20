<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'sale_id',
        'points',
        'type',
        'balance_after',
        'description',
        'created_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
