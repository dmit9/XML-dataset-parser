<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParameterValue extends Model
{
    protected $fillable = ['parameter_id', 'value'];

    public function parameter()
    {
        return $this->belongsTo(Parameter::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_parameters');
    }
}
