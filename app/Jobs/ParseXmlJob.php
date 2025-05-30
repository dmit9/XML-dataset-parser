<?php

namespace App\Jobs;

use App\Models\Import;
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
        $path = str_replace('\\', '/', $path);

        if (!file_exists($path)) {
            throw new \RuntimeException("XML-файл не найден: $path");
        }

        app(\App\Services\Xml\XmlCategoryParserService::class)->parse($path);
        $count = app(\App\Services\Xml\XmlOfferParserService::class)->parse($path, $import);

        $import->update([
            'parsed_offers' => $count,
            'status' => 'completed',
            'finished_at' => now(),
        ]);

        Log::info(">>> Завершено: обработано $count товаров");
    }
}
