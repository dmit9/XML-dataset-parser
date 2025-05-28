<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'available','name', 'price', 'description', 'description_format', 'vendor',
        'vendor_code', 'barcode', 'pictures', 'stock_quantity', 'category_id'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
