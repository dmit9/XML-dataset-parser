<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductParameter extends Pivot
{
    protected $table = 'product_parameters';

    public $timestamps = false;
}
