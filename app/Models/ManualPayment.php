<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'amount',
        'description',
        'type',
        'is_deposited',
        'deposited_at'
    ];

    protected $casts = [
        'is_deposited' => 'boolean',
        'deposited_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
