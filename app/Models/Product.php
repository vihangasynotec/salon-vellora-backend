<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'sku',
        'barcode',
        'cost_price',
        'retail_price',
        'stock_quantity',
        'alert_quantity',
        'unit',
        'image',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'alert_quantity' => 'integer',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class)->orderBy('created_at', 'desc');
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->alert_quantity;
    }
}
