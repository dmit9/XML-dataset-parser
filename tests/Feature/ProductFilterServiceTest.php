<?php

namespace Tests\Feature;

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Models\Product;
use App\Models\Parameter;
use App\Models\ParameterValue;
use App\Services\ProductFilterService;

class ProductFilterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtered_ids_and_counts()
    {
        parent::setUp();
        Redis::flushall();

        // Параметр
        $param = Parameter::create(['name' => 'Цвет', 'slug' => 'color']);
        $black = ParameterValue::create(['parameter_id' => $param->id, 'value' => 'Черный']);
        $white = ParameterValue::create(['parameter_id' => $param->id, 'value' => 'Белый']);

        // Продукты
        Product::create(['id' => 1, 'name' => 'A', 'price' => 1000]);
        Product::create(['id' => 2, 'name' => 'B', 'price' => 2000]);

        DB::table('product_parameters')->insert([
            ['product_id' => 1, 'parameter_value_id' => $black->id],
            ['product_id' => 2, 'parameter_value_id' => $white->id],
        ]);

        // Redis-множества
        Redis::sadd('filter:color:Черный', 1);
        Redis::sadd('filter:color:Белый', 2);
        Redis::sadd('all_product_ids', 1, 2);

        $service = new ProductFilterService();

        $ids = $service->getFilteredProductIds(['color' => 'Черный']);
        $this->assertEquals([1], $ids);

        $filters = $service->getFilterStats(['color' => 'Черный']);
        $this->assertTrue($filters[0]['values'][0]['active']);
        $this->assertEquals('Черный', $filters[0]['values'][0]['value']);
        Redis::flushall();

    }
}

