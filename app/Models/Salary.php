<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Salary extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'salaries';

    protected $fillable = [
        'salary_slip_number',
        'staff_id',
        'month',
        'year',
        'basic_salary',
        'monthly_target',
        'monthly_revenue',
        'target_achieved',
        'electricity_bill',
        'shop_rent',
        'petty_cash',
        'other_deductions',
        'total_expenses_deducted',
        'expense_breakdown',
        'remaining_amount',
        'incentive_percentage',
        'incentive_amount',
        'allowances',
        'advances',
        'net_salary',
        'payment_status',
        'payment_method',
        'paid_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'monthly_target' => 'decimal:2',
            'monthly_revenue' => 'decimal:2',
            'target_achieved' => 'boolean',
            'electricity_bill' => 'decimal:2',
            'shop_rent' => 'decimal:2',
            'petty_cash' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'total_expenses_deducted' => 'decimal:2',
            'expense_breakdown' => 'array',
            'remaining_amount' => 'decimal:2',
            'incentive_percentage' => 'decimal:2',
            'incentive_amount' => 'decimal:2',
            'allowances' => 'decimal:2',
            'advances' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
