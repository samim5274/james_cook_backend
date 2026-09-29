<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function subcategories()
    {
        return $this->hasMany(ExSubCategory::class, 'category_id', 'id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'category_id', 'id');
    }
}
