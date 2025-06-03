<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use App\Models\Category;
use App\Services\Xml\XmlCategoryParserService;
use App\Models\Import;

class XmlCategoryParserServiceTest extends TestCase
{
    public function test_parses_categories_correctly()
    {
        $xml = <<<XML
        <yml_catalog>
            <shop>
                <categories>
                    <category id="001">Электроника</category>
                    <category id="002" parentId="001">Ноутбуки</category>
                </categories>
            </shop>
        </yml_catalog>
        XML;

        $path = storage_path('framework/testing/test_categories.xml');
        file_put_contents($path, $xml);

        $import = Import::factory()->create();

        $service = new XmlCategoryParserService();
        $count = $service->parse($path, $import);

        $this->assertEquals(2, $count);
        $this->assertDatabaseHas('categories', ['id' => '001', 'name' => 'Электроника']);
        $this->assertDatabaseHas('categories', ['id' => '002', 'parent_id' => '001']);
    }
}

