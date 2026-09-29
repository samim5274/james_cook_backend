<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',

        'description',
        'image',

        'meta_title',
        'meta_description',
        'meta_keywords',

        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($brand) {

            if (!$brand->slug) {

                $brand->slug = self::generateUniqueSlug($brand->name);

            }
        });

        static::updating(function ($brand) {

            if ($brand->isDirty('name')) {

                $brand->slug = self::generateUniqueSlug(
                    $brand->name,
                    $brand->id
                );

            }
        });
    }

    public static function generateUniqueSlug($name, $ignoreId = null)
    {

        $slug = Str::slug($name);

        $original = $slug;

        $counter = 1;


        while(
            self::where('slug',$slug)
            ->when($ignoreId,function($query) use ($ignoreId){

                return $query->where('id','!=',$ignoreId);

            })
            ->exists()
        ){

            $slug = $original.'-'.$counter;

            $counter++;

        }


        return $slug;

    }


    // Relationships

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function coupons()
    {
        return $this->belongsToMany(
            Coupon::class,
            'coupon_brands'
        );
    }
}
