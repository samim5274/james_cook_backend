<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        // Basic
        'name',
        'slug',
        'description',
        'image',

        // SEO
        'meta_title',
        'meta_description',
        'meta_keywords',

        'og_title',
        'og_description',
        'og_image',
        'canonical_url',
        'robots',
        'indexable',

        // Status
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

        static::creating(function ($category) {

            $category->slug = $category->slug ?: Str::slug($category->name);

            $category->meta_title = $category->meta_title ?: Str::limit($category->name, 60, '');

            $category->meta_description = $category->meta_description
                ?: Str::limit(strip_tags($category->description ?? $category->name), 160, '');

            $category->meta_keywords = $category->meta_keywords
                ?: strtolower($category->name);

            $category->og_title = $category->og_title ?: $category->meta_title;

            $category->og_description = $category->og_description
                ?: $category->meta_description;

            $category->og_image = $category->og_image ?: $category->image;

            $category->robots = $category->robots ?: 'index,follow';

            $category->indexable = $category->indexable ?? true;
        });

        static::created(function ($category) {
            if (empty($category->canonical_url)) {
                $category->updateQuietly([
                    'canonical_url' => url("/category/{$category->slug}/{$category->id}")
                ]);
            }
        });
    }

    // Relationships
    public function subcategories()
    {
        return $this->hasMany(SubCategory::class)->where('is_active', true)->orderBy('sort_order', 'asc');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function coupons()
    {
        return $this->belongsToMany(
            Coupon::class,
            'coupon_categories',
            'category_id',
            'coupon_id'
        );
    }

    // Others
    public function getSeoTitleAttribute()
    {
        return $this->meta_title ?: $this->name;
    }

    public function getSeoDescriptionAttribute()
    {
        return $this->meta_description ?: $this->description;
    }

    public function getSeoImageAttribute()
    {
        return $this->og_image ?: $this->image;
    }

    public function getSeoCanonicalAttribute()
    {
        return $this->canonical_url ?: url('/category/' . $this->slug . '/' . $this->id);
    }
}
