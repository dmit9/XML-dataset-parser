<?php

namespace App\Services\Xml;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use XMLReader;

class XmlCategoryParserService
{
    public function parse(string $path): void
    {
        $reader = new XMLReader();
        $reader->open($path);

        Log::info(">>> START XmlCategoryParserService # $path");

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'category') {
                try {
                    $categoryNode = new SimpleXMLElement($reader->readOuterXML());
                    $id = (string) $categoryNode['id'];
                    $parentId = isset($categoryNode['parentId']) ? (string) $categoryNode['parentId'] : null;
                    $name = (string) $categoryNode;

                    DB::table('categories')->updateOrInsert(
                        ['id' => $id],
                        [
                            'name' => $name,
                            'parent_id' => $parentId,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error("Ошибка при разборе category: " . $e->getMessage());
                }
            }
        }

        $reader->close();
        Log::info(">>> END ParseXmlJob categories");
    }
}
