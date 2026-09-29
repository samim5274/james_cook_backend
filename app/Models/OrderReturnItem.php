<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnItem extends Model
{
    use HasFactory;

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
    | Product Condition Constants
    |--------------------------------------------------------------------------
    */

    public const CONDITION_RESELLABLE = 'resellable';
    public const CONDITION_DAMAGED = 'damaged';
    public const CONDITION_DEFECTIVE = 'defective';

    /*
    |--------------------------------------------------------------------------
    | Available Values
    |--------------------------------------------------------------------------
    */

    public const REASONS = [
        self::REASON_DEFECTIVE,
        self::REASON_WRONG_ITEM,
        self::REASON_DAMAGED_IN_TRANSIT,
        self::REASON_CUSTOMER_CHANGED_MIND,
        self::REASON_OTHER,
    ];

    public const CONDITIONS = [
        self::CONDITION_RESELLABLE,
        self::CONDITION_DAMAGED,
        self::CONDITION_DEFECTIVE,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'order_return_id',

        'cart_id',
        'product_id',
        'stock_id',

        'quantity',

        'unit_price',
        'unit_discount',

        'subtotal',
        'discount',
        'vat',
        'refund_amount',

        'reason',
        'condition',

        'restocked',
        'restocked_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute Casting
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'order_return_id' => 'integer',
        'cart_id' => 'integer',
        'product_id' => 'integer',
        'stock_id' => 'integer',

        'quantity' => 'integer',

        'unit_price' => 'decimal:2',
        'unit_discount' => 'decimal:2',

        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'vat' => 'decimal:2',
        'refund_amount' => 'decimal:2',

        'restocked' => 'boolean',
        'restocked_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(
            OrderReturn::class,
            'order_return_id'
        );
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(
            Cart::class,
            'cart_id'
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
            'product_id'
        );
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(
            Stock::class,
            'stock_id'
        );
    }
}
