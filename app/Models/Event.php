<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'user_id', 'name', 'date', 'total_price', 'collected_amount', 'overflow_balance', 'status', 'admin_notes', 'assigned_admin_id', 'category_id', 'city_id', 'address', 'is_location_public',
        'creator_budget', 'requests_internal_service', 'service_cost', 'service_adds_to_total'
    ];

    protected $casts = [
        'requests_internal_service' => 'boolean',
        'service_adds_to_total' => 'boolean',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function wishes()
    {
        return $this->hasMany(Wish::class);
    }

    public function manualPayments()
    {
        return $this->hasMany(ManualPayment::class);
    }
}
