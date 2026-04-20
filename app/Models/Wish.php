<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wish extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'name', 'description', 'target_amount', 'liquid_amount', 'current_amount', 'status'
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function contributions()
    {
        return $this->hasMany(Contribution::class);
    }
}
