<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'display_order',
        'status',
    ];

    public function services()
    {
        return $this->hasMany(Service::class, 'category_id')->orderBy('name');
    }
}
