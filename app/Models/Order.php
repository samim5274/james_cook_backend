<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_METHOD_CARD = 'card';
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_METHOD_BKASH = 'bkash';
    public const PAYMENT_METHOD_NAGAD = 'nagad';
    public const PAYMENT_METHOD_ROCKET = 'rocket';
    public const PAYMENT_METHOD_WALLET = 'wallet';

    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_CASH,
        self::PAYMENT_METHOD_CARD,
        self::PAYMENT_METHOD_BANK_TRANSFER,
        self::PAYMENT_METHOD_BKASH,
        self::PAYMENT_METHOD_NAGAD,
        self::PAYMENT_METHOD_ROCKET,
        self::PAYMENT_METHOD_WALLET,
    ];

    public const CURRENCY_BDT = 'BDT';

    public const STATUS_PENDING = 'pending';
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_PARTIALLY_RETURNED = 'partially_returned';

    public const ORDER_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNPAID,
        self::STATUS_PARTIALLY_PAID,
        self::STATUS_COMPLETED,
        self::STATUS_PARTIALLY_RETURNED,
        self::STATUS_RETURNED,
    ];

    protected $fillable = [
        'reg',
        'order_number',
        'slug',
        'order_date',

        'user_id',

        'customer_id',
        'customer_name',
        'customer_phone',

        'subtotal',
        'discount',
        'vat_percentage',
        'vat',

        'due_amount',
        'payable_amount',
        'refunded_amount',

        'payment_method',
        'currency',
        'paid_at',

        'point',

        'status',

        'completed_at',
        'returned_at',

        'returned_by',

        'remarks',

        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'order_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'vat' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'payable_amount' => 'decimal:2',
        'point' => 'integer',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'returned_at' => 'datetime',
        'refunded_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'currency' => self::CURRENCY_BDT,
        'payment_method' => self::PAYMENT_METHOD_CASH,
        'status' => self::STATUS_PENDING,
        'subtotal' => 0,
        'discount' => 0,
        'vat_percentage' => 0,
        'vat' => 0,
        'due_amount' => 0,
        'payable_amount' => 0,
        'point' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {

            if (blank($order->reg)) {
                $order->reg = static::generateReg();
            }

            if (blank($order->order_number)) {
                $order->order_number = static::generateOrderNumber();
            }

            if (blank($order->slug)) {
                $order->slug = static::generateSlug($order);
            }

            if (blank($order->order_date)) {
                $order->order_date = now()->toDateString();
            }
        });
    }


    protected static function generateReg(): string
    {
        do {
            $reg = 'ORD-' . strtoupper(Str::random(12));
        } while (static::where('reg', $reg)->exists());

        return $reg;
    }


    protected static function generateOrderNumber(): string
    {
        do {
            $number = 'POS-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }


    protected static function generateSlug(Order $order): string
    {
        do {
            $slug = Str::slug(
                'order-' . ($order->order_number ?: Str::random(10))
            );

        } while (static::where('slug', $slug)->exists());

        return $slug;
    }


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }


    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class, 'order_id');
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class, 'reg', 'reg');
    }

}
