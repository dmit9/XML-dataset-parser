<?php

namespace App\Services\Xml;

use App\Models\Parameter;
use App\Models\ParameterValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleXMLElement;
use XMLReader;
use App\Models\Import;

class XmlOfferParserService
{
    public function parse(string $path, Import $import): int
    {
        $reader = new XMLReader();
        if (!$reader->open($path)) {
            throw new \RuntimeException("Не удалось открыть XML: $path");
        }

        Log::info(">>> START XmlOfferParserService parsing");

        // Кэш для ускорения
        $paramCache = [];     // [$slug][$value] => value_id
        $slugCache = [];      // [$name] => slug
        $parameterIds = [];   // [$slug] => parameter_id

        $counter = 0;
        $skipped = 0;

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'offer') {
                $node = new SimpleXMLElement($reader->readOuterXML());
                $reader->next();

                $productId = (int) $node['id'];
                $available = filter_var((string) $node['available'], FILTER_VALIDATE_BOOLEAN);
                $name = (string) $node->name;
                $price = (float) $node->price;
                $desc = (string) $node->description ?? '';
                $desc_f = (string) $node->description_format ?? '';
                $categoryId = (string) $node->categoryId;
                $vendor = (string) $node->vendor;
                $vendorCode = (string) $node->vendorCode;
                $barcode = (string) $node->barcode ?? null;
                $stock = (int) $node->count ?? 0;

                if (!$productId || !$name || !$price) {
                    Log::warning("Пропущен товар: id={$productId}, name='{$name}', price={$price}");
                    $skipped++;
                    continue;
                }

                // Картинки
                $pictures = [];
                foreach ($node->picture as $pic) {
                    $url = trim((string) $pic);
                    if ($url) $pictures[] = $url;
                }
                $picturesJson = json_encode($pictures);

                // Вставка/обновление продукта
                DB::table('products')->updateOrInsert(
                    ['id' => $productId],
                    [
                        'available' => $available,
                        'name' => $name,
                        'price' => $price,
                        'description' => $desc,
                        'description_format' => $desc_f,
                        'category_id' => $categoryId,
                        'vendor' => $vendor,
                        'vendor_code' => $vendorCode,
                        'barcode' => $barcode,
                        'stock_quantity' => $stock,
                        'pictures' => $picturesJson,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                // Обработка параметров
                $paramIds = [];

                foreach ($node->param as $param) {
                    $paramName = (string) $param['name'];
                    $paramValue = trim((string) $param);
                    if (!$paramValue) continue;

                    $slug = $slugCache[$paramName] ??= Str::slug($paramName, '_');

                    if (!isset($parameterIds[$slug])) {
                        $paramModel = Parameter::firstOrCreate(
                            ['name' => $paramName],
                            ['slug' => $slug]
                        );
                        $parameterIds[$slug] = $paramModel->id;
                    }

                    $parameterId = $parameterIds[$slug];

                    if (!isset($paramCache[$slug][$paramValue])) {
                        $valueModel = ParameterValue::firstOrCreate(
                            ['parameter_id' => $parameterId, 'value' => $paramValue]
                        );
                        $paramCache[$slug][$paramValue] = $valueModel->id;
                    }

                    $paramValueId = $paramCache[$slug][$paramValue];
                    $paramIds[] = $paramValueId;

                    // Redis: добавим товар в множество filter:slug:value
                    Redis::sadd("filter:{$slug}:{$paramValue}", $productId);
                    Redis::sadd("all_product_ids", $productId); // для начального запроса без фильтров
                }

                // Обновляем product_parameters
                DB::table('product_parameters')->where('product_id', $productId)->delete();
                foreach ($paramIds as $valId) {
                    DB::table('product_parameters')->insert([
                        'product_id' => $productId,
                        'parameter_value_id' => $valId,
                    ]);
                }

                $counter++;
                if ($counter % 100 === 0) {
                    $import->update(['parsed_offers' => $counter]);
                }
            }
        }

        $reader->close();
        Log::info(">>> END XmlOfferParserService parsing, total = $counter");
        Log::info(">>> END parsing: успешно = $counter, пропущено = $skipped");

        return $counter;
    }
}
