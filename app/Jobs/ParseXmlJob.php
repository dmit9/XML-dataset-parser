<?php
namespace App\Jobs;

use App\Models\Import;
use App\Models\Parameter;
use App\Models\ParameterValue;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
//use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;
use SimpleXMLElement;
use XMLReader;
use Throwable;
use Illuminate\Support\Str;

class ParseXmlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $importId;

    public function __construct(int $importId)
    {
        $this->importId = $importId;
    }

    public function handle(): void
    {
        Log::info(">>> START ParseXmlJob for import #{$this->importId}");

        $import = Import::findOrFail($this->importId);

        $path = storage_path("app/imports/{$import->id}.xml");
        $path = str_replace('\\', '/', $path); // на всякий случай для Windows

        if (!file_exists($path)) {
            throw new \RuntimeException("XML-файл не найден: $path");
        }

        $reader = new XMLReader();
        if (!$reader->open($path)) {
            throw new \RuntimeException("Не удалось открыть XML: $path");
        }

        $paramCache = [];     // [$slug][$value] => value_id
        $slugCache = [];      // [$name] => slug
        $parameterIds = [];   // [$slug] => parameter_id
        $counter = 0;

// === Первый проход: только <category> ===
        $reader = new XMLReader();
        $reader->open($path);

        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === 'category') {
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

        $reader = new XMLReader();
        $reader->open($path);

        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === 'offer') {
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
                $pictures = [];
                foreach ($node->picture as $pic) {
                    $url = trim((string) $pic);
                    if ($url) $pictures[] = $url;
                }
                $picturesJson = json_encode($pictures);

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

                $paramIds = [];

                foreach ($node->param as $param) {
                    $paramName = (string) $param['name'];
                    $paramValue = trim((string) $param);
                    if (!$paramValue) continue;

                    $slug = $slugCache[$paramName] ??= Str::slug($paramName);

                    if (!isset($parameterIds[$slug])) {
                        $paramModel = Parameter::firstOrCreate([
                            'slug' => $slug,
                        ], [
                            'name' => $paramName,
                        ]);
                        $parameterIds[$slug] = $paramModel->id;
                    }

                    $parameterId = $parameterIds[$slug];

                    if (!isset($paramCache[$slug][$paramValue])) {
                        $valueModel = ParameterValue::firstOrCreate([
                            'parameter_id' => $parameterId,
                            'value' => $paramValue,
                        ]);
                        $paramCache[$slug][$paramValue] = $valueModel->id;
                    }

                    $paramValueId = $paramCache[$slug][$paramValue];
                    $paramIds[] = $paramValueId;

               //     Redis::sadd("filter:{$slug}:{$paramValue}", $productId);
                }

                // обновляем связи
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

        $import->update([
            'parsed_offers' => $counter,
            'status' => 'completed',
            'finished_at' => now(),
        ]);

        Log::info(">>> Завершено: обработано $counter товаров");
    }

    public function failed(Throwable $e): void
    {
        Log::error("ParseXmlJob failed: " . $e->getMessage());
        Import::where('id', $this->importId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}
