<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        // Basic Information
        'name',
        'email',
        'user_id',
        'phone',
        'password',
        'photo',

        // Personal Information
        'dob',
        'gender',
        'blood_group',
        'national_id',
        'religion',

        // Role
        'role',
        'designation',

        // Status
        'is_active',
        'is_profile_completed',

        // Address
        'present_address',
        'permanent_address',

        // Verification
        'email_verified_at',
        'phone_verified_at',
        'otp',
        'otp_expires_at',

        // Social Login
        'facebook_id',
        'google_id',
        'github_id',

        // Login Information
        'last_login_at',
        'last_login_ip',

        // Point System
        'total_point',
    ];

    /**
     * Hidden attributes.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp',
    ];

    protected $casts = [
        'email_verified_at'  => 'datetime',
        'phone_verified_at'  => 'datetime',
        'otp_expires_at'     => 'datetime',
        'last_login_at'      => 'datetime',
        'dob'                => 'date',
        'is_active'          => 'boolean',
        'is_profile_completed' => 'boolean',
        'last_login_ip'      => 'string',
        'total_point'        => 'integer',
        'password'           => 'hashed',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->password)) {
                $user->password = Hash::make('password');
            }
        });
    }

    public function order(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payment(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }
}
