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
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
