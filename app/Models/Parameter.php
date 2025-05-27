<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parameter extends Model
{
    protected $fillable = ['name', 'slug'];

    public function values()
    {
        return $this->hasMany(ParameterValue::class);
    }
}
