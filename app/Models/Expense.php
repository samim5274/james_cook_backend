<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sub_category_id',
        'user_id',
        'title',
        'date',
        'amount',
        'remark'
    ];

    protected $casts = [
        'date'      => 'date:Y-m-d',
        'amount'    => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(ExCategory::class, 'category_id', 'id');
    }

    public function subcategory()
    {
        return $this->belongsTo(ExSubCategory::class, 'sub_category_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
