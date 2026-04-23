<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    public function accountTypes()
    {
        return $this->belongsToMany(AccountType::class, 'bank_account_type');
    }
}
