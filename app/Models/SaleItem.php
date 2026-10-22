<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'item_type',
        'item_id',
        'rental_order_id',
        'rental_return_due',
        'rental_deposit',
        'name',
        'staff_id',
        'unit_price',
        'quantity',
        'discount_amount',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
