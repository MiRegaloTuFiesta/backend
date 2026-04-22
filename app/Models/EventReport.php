<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventReport extends Model
{
    protected $fillable = ['event_id', 'reporter_email', 'reason', 'status'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
