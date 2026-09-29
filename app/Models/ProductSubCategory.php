<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSubCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'image',

        // SEO
        'meta_title',
        'meta_description',
        'meta_keywords',

        // Open Graph
        'og_title',
        'og_description',
        'og_image',

        // SEO Control
        'canonical_url',
        'robots',
        'indexable',

        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'indexable' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();


        static::creating(function ($subCategory) {

            if (empty($subCategory->slug)) {

                $subCategory->slug = Str::slug($subCategory->name);

            }

        });


        static::updating(function ($subCategory) {

            if ($subCategory->isDirty('name') && empty($subCategory->slug)) {

                $subCategory->slug = Str::slug($subCategory->name);

            }

        });

    }

    // Relationships

    public function category()
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
