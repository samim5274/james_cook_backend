<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PurchaseOrderPayment extends Model
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
        'supplier_id',
        'payment_number',
        'receipt_no',
        'payment_type',
        'payment_method',
        'amount',
        'discount',
        'currency',
        'paid_at',
        'verified_by',
        'verified_at',
        'received_by',
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
        static::creating(function (PurchaseOrderPayment $payment) {
            if (blank($payment->payment_number)) {
                $payment->payment_number = static::generatePaymentNumber();
            }

            if (blank($payment->receipt_no)) {
                $payment->receipt_no = static::generateReceiptNumber();
            }

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

    public static function generatePaymentNumber(): string
    {
        do {
            $number = 'PAY-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
        } while (static::where('payment_number', $number)->exists());

        return $number;
    }

    public static function generateReceiptNumber(): string
    {
        do {
            $number = 'RCP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(8));
        } while (static::where('receipt_no', $number)->exists());

        return $number;
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplyer::class, 'supplier_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
