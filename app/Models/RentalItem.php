<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'category',
        'description',
        'rental_price_per_24h',
        'security_deposit',
        'late_fee_per_day',
        'total_quantity',
        'available_quantity',
        'condition',
        'image',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'rental_price_per_24h' => 'decimal:2',
            'security_deposit' => 'decimal:2',
            'late_fee_per_day' => 'decimal:2',
            'total_quantity' => 'integer',
            'available_quantity' => 'integer',
        ];
    }

    public function orders()
    {
        return $this->hasMany(RentalOrder::class)->orderBy('created_at', 'desc');
    }

    public function activeOrders()
    {
        return $this->hasMany(RentalOrder::class)->where('status', 'active');
    }

    public function isAvailable(): bool
    {
        return $this->available_quantity > 0 && $this->status === 'available';
    }
}
