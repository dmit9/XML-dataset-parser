<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Import extends Model
{
    use HasFactory;
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
