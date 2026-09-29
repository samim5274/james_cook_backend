<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'reg',
        'user_id',
        'product_id',
        'stock_id',
        'quantity',
        'returned_quantity',
        'price',
        'discount',
        'total_amount',
        'point',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price'    => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    // === Accessors & Logic ===

    public function getTotalPriceAttribute(): float
    {
        return ($this->price - $this->discount) * $this->quantity;
    }

    public function scopeCurrent($query, $regId)
    {
        return $query->where(function ($q) use ($regId) {
            $q->where('user_id', auth()->id())
            ->where('reg', $regId);
        });
    }
}
