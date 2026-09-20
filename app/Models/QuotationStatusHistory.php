<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationStatusHistory extends Model
{
    public $timestamps = false;
    protected $table = 'quotation_status_history';

    protected $fillable = [
        'quotation_id',
        'status_from',
        'status_to',
        'changed_by',
        'notes',
        'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
