<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'bank_id',
        'account_type_id',
        'account_number',
        'bank_rut',
        'profile_photo_path',
        'is_profile_photo_public',
        'is_blocked',
        'blocked_until',
    ];

    protected $appends = ['profile_photo_url', 'is_currently_blocked'];


    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function accountType()
    {
        return $this->belongsTo(AccountType::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_profile_photo_public' => 'boolean',
            'is_blocked' => 'boolean',
            'blocked_until' => 'datetime',
        ];
    }

    public function getIsCurrentlyBlockedAttribute()
    {
        if ($this->is_blocked) {
            return true;
        }
        if ($this->blocked_until && $this->blocked_until->isFuture()) {
            return true;
        }
        return false;
    }

    public function getProfilePhotoUrlAttribute()
    {
        return $this->profile_photo_path 
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->profile_photo_path) 
            : null;
    }


    public function events()
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification());
    }
}
