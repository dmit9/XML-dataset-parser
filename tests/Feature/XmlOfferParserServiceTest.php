<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Import;
use App\Services\Xml\XmlOfferParserService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Testing\RefreshDatabase;

class XmlOfferParserServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_offer_is_parsed_and_inserted()
    {
        parent::setUp();
        Redis::flushall(); // очистка Redis

        $xml = <<<XML
        <yml_catalog>
            <shop>
                <offers>
                    <offer id="1001" available="true">
                        <name>Товар 1</name>
                        <price>1999.99</price>
                        <categoryId>001</categoryId>
                        <vendor>Samsung</vendor>
                        <vendorCode>S123</vendorCode>
                        <barcode>1234567890</barcode>
                        <count>5</count>
                        <param name="Цвет">Черный</param>
                        <picture>http://example.com/img.jpg</picture>
                    </offer>
                </offers>
            </shop>
        </yml_catalog>
        XML;

        $path = storage_path('framework/testing/test_offer.xml');
        file_put_contents($path, $xml);

        \App\Models\Category::create(['id' => uniqid(), 'name' => 'Категория']);

        $import = Import::factory()->create();
        $parser = new XmlOfferParserService();
        $count = $parser->parse($path, $import);

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('products', ['id' => 1001, 'name' => 'Товар 1', 'price' => 1999.99]);
        $this->assertTrue(Redis::sismember('filter:color:Черный', 1001));

        Redis::flushall();
    }
}

