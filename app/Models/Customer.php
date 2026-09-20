<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'birthday',
        'notes',
        'loyalty_points',
        'total_spend',
        'total_visits',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'loyalty_points' => 'integer',
            'total_spend' => 'decimal:2',
            'total_visits' => 'integer',
        ];
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class)->orderBy('created_at', 'desc');
    }
}
