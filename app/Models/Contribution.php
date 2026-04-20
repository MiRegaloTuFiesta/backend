<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'wish_id', 'donor_name', 'email', 'rut', 'amount', 'payment_id', 'status', 'payment_method',
        'platform_fee', 'gateway_fee', 'net_to_user'
    ];

    public function wish()
    {
        return $this->belongsTo(Wish::class);
    }
}
