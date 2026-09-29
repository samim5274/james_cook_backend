<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'reg',
        'user_id',
        'product_id',
        'quantity',
        'price',
        'total_amount',
        'sale_price',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected $appends = [
        'gross_amount',
    ];

    // Cart owner
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Purchase order
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'reg', 'reg');
    }

    public function getGrossAmountAttribute()
    {
        return round(
            (float) $this->quantity * (float) $this->price,
            2
        );
    }

}
