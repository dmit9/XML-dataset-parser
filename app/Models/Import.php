<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    protected $fillable = [
        'url',
        'status',
        'total_bytes',
        'downloaded_bytes',
        'parsed_offers',
        'total_offers',
        'error',
        'started_at',
        'finished_at',
    ];
}
