<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OtpVerification extends Model
{
    use HasFactory;

    protected $table = 'otp_verifications';

    protected $fillable = [
        'transaction_id',
        'user_id',
        'otp',
        'type',
        
        'expired_at',
        'is_used',
        'ip_address',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
        'is_used'    => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationship
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    // check OTP expired or not
    public function isExpired(): bool
    {
        return Carbon::now()->greaterThan($this->expired_at);
    }

    // check valid OTP
    public function isValid(): bool
    {
        return !$this->is_used && !$this->isExpired();
    }

    // mark OTP as used
    public function markAsUsed(): bool
    {
        return $this->update([
            'is_used' => true
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_used', false)
                     ->where('expired_at', '>', now());
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
