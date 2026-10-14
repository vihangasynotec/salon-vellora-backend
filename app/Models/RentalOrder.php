<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'rental_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_nic',
        'rental_item_id',
        'quantity',
        'rental_start_date',
        'duration_hours',
        'due_return_date',
        'actual_return_date',
        'rental_fee',
        'deposit_amount',
        'total_collected',
        'deposit_refunded',
        'late_fee_charged',
        'damage_fee_charged',
        'payment_method',
        'status',
        'sale_id',
        'created_by',
        'notes',
        'return_condition_notes',
    ];

    protected function casts(): array
    {
        return [
            'rental_start_date' => 'datetime',
            'due_return_date' => 'datetime',
            'actual_return_date' => 'datetime',
            'quantity' => 'integer',
            'duration_hours' => 'integer',
            'rental_fee' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'total_collected' => 'decimal:2',
            'deposit_refunded' => 'decimal:2',
            'late_fee_charged' => 'decimal:2',
            'damage_fee_charged' => 'decimal:2',
        ];
    }

    public function item()
    {
        return $this->belongsTo(RentalItem::class, 'rental_item_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        if ($this->status === 'returned' || $this->status === 'cancelled') {
            return false;
        }
        return Carbon::now()->greaterThan($this->due_return_date);
    }

    public function hoursRemaining(): int
    {
        if ($this->isOverdue()) {
            return 0;
        }
        return (int) Carbon::now()->diffInHours($this->due_return_date, false);
    }
}
