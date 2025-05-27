<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name', 'price', 'description', 'vendor',
        'vendor_code', 'barcode', 'category_id', 'stock_quantity'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function parameterValues()
    {
        return $this->belongsToMany(ParameterValue::class, 'product_parameters');
    }
}
