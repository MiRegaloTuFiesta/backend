<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    public function banks()
    {
        return $this->belongsToMany(Bank::class, 'bank_account_type');
    }
}
