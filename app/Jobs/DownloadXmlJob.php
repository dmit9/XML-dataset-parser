<?php

namespace App\Jobs;

use App\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use Throwable;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Jobs\ParseXmlJob;

class DownloadXmlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $importId;

    public function __construct(int $importId)
    {
        $this->importId = $importId;
    }

    public function handle(): void
    {
        Log::info(">>> START DownloadXmlJob for import #{$this->importId}");

        $import = Import::findOrFail($this->importId);
        $client = new Client();
        $offset = $import->downloaded_bytes ?? 0;

        $url = $import->url;
        $tmpPath = storage_path("app/imports/{$import->id}.xml.tmp");
        $finalPath = storage_path("app/imports/{$import->id}.xml");

        Log::info("Загружаем файл по адресу: $url");

        $headers = $offset > 0 ? ['Range' => "bytes={$offset}-"] : [];

        $response = $client->request('GET', $url, [
            'stream' => true,
            'headers' => $headers,
            'timeout' => 60,
        ]);

        $total = $import->total_bytes ?? $this->getTotalSize($response, $offset);
        $import->update(['total_bytes' => $total]);

        $fh = fopen($tmpPath, $offset ? 'ab' : 'wb');
        $body = $response->getBody();

        while (!$body->eof()) {
            $chunk = $body->read(1024 * 512); // 512 KB
            fwrite($fh, $chunk);
            $import->increment('downloaded_bytes', strlen($chunk));
        }

        fclose($fh);

        Log::info(">>> Загрузка завершена. Перемещаем tmp → xml");

        if (!file_exists($tmpPath)) {
            throw new \RuntimeException("Файл не найден: $tmpPath");
        }

        rename($tmpPath, $finalPath);

        if (!file_exists($finalPath)) {
            throw new \RuntimeException("Файл не переместился: $finalPath");
        }

        Log::info(">>> Файл успешно перемещён: $finalPath");

        $import->update([
            'status' => 'parsing',
            'started_at' => now(),
        ]);

        Log::info("Отправляем ParseXmlJob с importId = {$import->id}");
        ParseXmlJob::dispatch($import->id);
    }

    protected function getTotalSize($response, int $offset): ?int
    {
        $contentRange = $response->getHeaderLine('Content-Range');
        if (preg_match('/\/(\d+)$/', $contentRange, $matches)) {
            return (int) $matches[1];
        }

        $length = $response->getHeaderLine('Content-Length');
        return $offset + ($length ? (int) $length : 0);
    }

    public function failed(Throwable $e): void
    {
        Log::error("DownloadXmlJob failed: " . $e->getMessage());
        Import::where('id', $this->importId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}
