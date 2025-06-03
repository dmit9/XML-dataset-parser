<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Import;

class ImportFactory extends Factory
{
    protected $model = Import::class;

    public function definition(): array
    {
        return [
            'url' => 'https://example.com/test.xml',
            'status' => 'parsing',
            'downloaded_bytes' => 0,
            'total_bytes' => 0,
            'parsed_offers' => 0,
            'started_at' => now(),
        ];
    }
}


