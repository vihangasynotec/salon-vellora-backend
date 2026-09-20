<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'original_total',
        'package_price',
        'discount',
        'duration_minutes',
        'image',
        'is_featured',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'original_total' => 'decimal:2',
            'package_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'duration_minutes' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_package_items', 'package_id', 'service_id')->withPivot('quantity');
    }
}
