<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        // Relations
        'category_id',
        'subcategory_id',
        'brand_id',

        // Basic Information
        'name',
        'slug',
        'sku',

        // Description
        'summary',
        'description',

        // Pricing
        'purchase_price',
        'price',
        'discount',

        // Inventory
        'stock_quantity',
        'min_stock',

        // Status
        'is_active',
        'admin_remark',

        // Point System
        'point',
    ];

    protected $casts = [
        'purchase_price'  => 'decimal:2',
        'price'           => 'decimal:2',
        'discount'        => 'decimal:2',

        'stock_quantity'  => 'integer',
        'min_stock'       => 'integer',

        'is_active'       => 'boolean',

        'point'           => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            ProductCategory::class,
            'category_id'
        );
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(
            ProductSubCategory::class,
            'subcategory_id'
        );
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(
            Brand::class,
            'brand_id'
        );
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock;
    }
}
