<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Status Constants
    |--------------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';

    /*
    |--------------------------------------------------------------------------
    | Refund Status Constants
    |--------------------------------------------------------------------------
    */

    public const REFUND_STATUS_PENDING = 'pending';
    public const REFUND_STATUS_PROCESSED = 'processed';
    public const REFUND_STATUS_FAILED = 'failed';

    /*
    |--------------------------------------------------------------------------
    | Return Reason Constants
    |--------------------------------------------------------------------------
    */

    public const REASON_DEFECTIVE = 'defective';
    public const REASON_WRONG_ITEM = 'wrong_item';
    public const REASON_DAMAGED_IN_TRANSIT = 'damaged_in_transit';
    public const REASON_CUSTOMER_CHANGED_MIND = 'customer_changed_mind';
    public const REASON_OTHER = 'other';

    /*
    |--------------------------------------------------------------------------
    | Available Statuses
    |--------------------------------------------------------------------------
    */

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_COMPLETED,
    ];

    public const REFUND_STATUSES = [
        self::REFUND_STATUS_PENDING,
        self::REFUND_STATUS_PROCESSED,
        self::REFUND_STATUS_FAILED,
    ];

    public const REASONS = [
        self::REASON_DEFECTIVE,
        self::REASON_WRONG_ITEM,
        self::REASON_DAMAGED_IN_TRANSIT,
        self::REASON_CUSTOMER_CHANGED_MIND,
        self::REASON_OTHER,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'order_id',
        'user_id',
        'customer_id',
        'reg',

        'subtotal',
        'discount',
        'vat_percentage',
        'vat',
        'refund_amount',

        'refund_method',
        'refund_status',

        'status',
        'reason',

        'approved_by',
        'approved_at',

        'remarks',
        'ip_address',
        'user_agent',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute Casting
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'order_id' => 'integer',
        'user_id' => 'integer',
        'customer_id' => 'integer',
        'approved_by' => 'integer',

        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'vat' => 'decimal:2',
        'refund_amount' => 'decimal:2',

        'approved_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            OrderReturnItem::class,
            'order_return_id'
        );
    }
}
