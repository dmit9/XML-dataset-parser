<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\Product;
use App\Models\Parameter;
use App\Models\ParameterValue;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Str;
use SimpleXMLElement;
use XMLReader;
use Throwable;

class ParseXmlJob implements ShouldQueue
{
    use  Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $importId;

    public function __construct(int $importId)
    {
        $this->importId = $importId;
    }

    public function handle(): void
    {
        $import = Import::findOrFail($this->importId);
        $path = storage_path("app/imports/{$import->id}.xml");

        $reader = new XMLReader();
        $reader->open($path);

        $paramCache = []; // [$slug][$value] = parameter_value_id
        $slugCache = [];  // [$name] = slug
        $parameterIds = []; // [$slug] = parameter_id
        $batchSize = 100;
        $counter = 0;

        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === 'offer') {
                $node = new SimpleXMLElement($reader->readOuterXML());
                $reader->next(); // move to next

                // === Основные поля ===
                $productId = (int) $node['id'];
                $name = (string) $node->name;
                $price = (float) $node->price;
                $desc = (string) $node->description ?? '';
                $categoryId = (int) $node->categoryId;
                $vendor = (string) $node->vendor;
                $vendorCode = (string) $node->vendorCode;
                $barcode = (string) $node->barcode ?? null;
                $stock = (int) $node->count ?? 0;

                DB::table('products')->updateOrInsert(
                    ['id' => $productId],
                    [
                        'name' => $name,
                        'price' => $price,
                        'description' => $desc,
                        'category_id' => $categoryId,
                        'vendor' => $vendor,
                        'vendor_code' => $vendorCode,
                        'barcode' => $barcode,
                        'stock_quantity' => $stock,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                // === Обработка <param> ===
                $paramIds = [];

                foreach ($node->param as $param) {
                    $paramName = (string) $param['name'];
                    $paramValue = trim((string) $param);

                    if (!$paramValue) continue;

                    // Транслитим name → slug
                    $slug = $slugCache[$paramName] ??= Str::slug($paramName);

                    // Получаем или создаём параметр
                    if (!isset($parameterIds[$slug])) {
                        $paramModel = Parameter::firstOrCreate([
                            'slug' => $slug,
                        ], [
                            'name' => $paramName,
                        ]);
                        $parameterIds[$slug] = $paramModel->id;
                    }

                    $parameterId = $parameterIds[$slug];

                    // Получаем или создаём значение параметра
                    if (!isset($paramCache[$slug][$paramValue])) {
                        $valueModel = ParameterValue::firstOrCreate([
                            'parameter_id' => $parameterId,
                            'value' => $paramValue,
                        ]);
                        $paramCache[$slug][$paramValue] = $valueModel->id;
                    }

                    $parameterValueId = $paramCache[$slug][$paramValue];
                    $paramIds[] = $parameterValueId;

                    // Обновляем Redis
                    Redis::sadd("filter:{$slug}:{$paramValue}", $productId);
                }

                // Привязка параметров к товару (очищаем и вставляем заново)
                DB::table('product_parameters')->where('product_id', $productId)->delete();
                foreach ($paramIds as $valueId) {
                    DB::table('product_parameters')->insert([
                        'product_id' => $productId,
                        'parameter_value_id' => $valueId,
                    ]);
                }

                // === Прогресс ===
                $counter++;
                if ($counter % $batchSize === 0) {
                    $import->update(['parsed_offers' => $counter]);
                }
            }
        }

        $import->update([
            'parsed_offers' => $counter,
            'status' => 'completed',
            'finished_at' => now(),
        ]);

        $reader->close();
    }

    public function failed(Throwable $e): void
    {
        Log::error("ParseXmlJob failed: {$e->getMessage()}");
        Import::where('id', $this->importId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}
