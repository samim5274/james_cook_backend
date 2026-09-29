<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'purchase_orders';

    public const STATUS_PENDING = 'pending';
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_RETURNED = 'returned';

    public const CURRENCY_BDT = 'BDT';

    protected $fillable = [
        'reg',
        'order_number',
        'slug',
        'order_date',
        'user_id',
        'supplier_id',
        'supplier_name',
        'supplier_phone',
        'subtotal',
        'discount',
        'vat_percentage',
        'vat',
        'due_amount',
        'payable_amount',
        'payment_method',
        'currency',
        'status',
        'completed_at',
        'returned_at',
        'returned_by',
        'paid_at',
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
        'completed_at' => 'datetime',
        'returned_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected $appends = [
        'paid_amount',
        'is_paid',
        'is_due',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplyer::class, 'supplier_id');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseCart::class, 'reg', 'reg');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchaseOrderPayment::class, 'order_id','id');
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->payments()->where('payment_type', PurchaseOrderPayment::TYPE_PAYMENT)->sum('amount');
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->paid_amount >= (float) $this->payable_amount;
    }

    public function getIsDueAttribute(): bool
    {
        return (float) $this->due_amount > 0;
    }
}
