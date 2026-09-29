<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrderPayment extends Model
{
    use HasFactory;

    public const CURRENCY_BDT = 'BDT';

    public const METHOD_CASH = 'cash';
    public const METHOD_CARD = 'card';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_BKASH = 'bkash';
    public const METHOD_NAGAD = 'nagad';
    public const METHOD_ROCKET = 'rocket';
    public const METHOD_WALLET = 'wallet';

    public const PAYMENT_METHODS = [
        self::METHOD_CASH,
        self::METHOD_CARD,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_BKASH,
        self::METHOD_NAGAD,
        self::METHOD_ROCKET,
        self::METHOD_WALLET,
    ];

    public const TYPE_PAYMENT = 'payment';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const PAYMENT_TYPES = [
        self::TYPE_PAYMENT,
        self::TYPE_REFUND,
        self::TYPE_ADJUSTMENT,
    ];

    protected $fillable = [
        'order_id',
        'user_id',
        'customer_id',
        'verified_by',
        'received_by',

        'payment_number',
        'receipt_no',

        'payment_type',
        'payment_method',
        'amount',
        'discount',
        'currency',

        'paid_at',

        'verified_at',

        'remarks',

        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'amount' => 'decimal:2',

        'paid_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    protected $attributes = [
        'payment_type' => self::TYPE_PAYMENT,
        'payment_method' => self::METHOD_CASH,
        'currency' => self::CURRENCY_BDT,
        'amount' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderPayment $payment) {

            /*
            |--------------------------------------------------------------------------
            | Payment Number
            |--------------------------------------------------------------------------
            */

            if (blank($payment->payment_number)) {
                $payment->payment_number = static::generatePaymentNumber();
            }


            /*
            |--------------------------------------------------------------------------
            | Receipt Number
            |--------------------------------------------------------------------------
            */

            if (blank($payment->receipt_no)) {
                $payment->receipt_no = static::generateReceiptNumber();
            }


            /*
            |--------------------------------------------------------------------------
            | Defaults
            |--------------------------------------------------------------------------
            */

            if (blank($payment->currency)) {
                $payment->currency = self::CURRENCY_BDT;
            }

            if (blank($payment->payment_type)) {
                $payment->payment_type = self::TYPE_PAYMENT;
            }

            if (blank($payment->payment_method)) {
                $payment->payment_method = self::METHOD_CASH;
            }

            if (blank($payment->paid_at)) {
                $payment->paid_at = now();
            }
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Payment Number
    |--------------------------------------------------------------------------
    */

    public static function generatePaymentNumber(): string
    {
        do {
            $number =
                'PAY-' .
                now()->format('Ymd') .
                '-' .
                strtoupper(Str::random(8));

        } while (
            static::where('payment_number', $number)->exists()
        );

        return $number;
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Receipt Number
    |--------------------------------------------------------------------------
    */

    public static function generateReceiptNumber(): string
    {
        do {
            $number =
                'RCP-' .
                now()->format('Ymd') .
                '-' .
                strtoupper(Str::random(8));

        } while (
            static::where('receipt_no', $number)->exists()
        );

        return $number;
    }


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

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }
}
