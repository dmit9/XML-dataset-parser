<?php

namespace App\Jobs;

use App\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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
        $url = $import->url;

        $tmpPath = storage_path("app/imports/{$import->id}.xml.tmp");
        $finalPath = storage_path("app/imports/{$import->id}.xml");

        // Определяем, сколько уже скачано
        $offset = file_exists($tmpPath) ? filesize($tmpPath) : 0;

        // Получаем общий размер
        $totalSize = $this->getTotalSize($url);
        $import->update(['total_bytes' => $totalSize]);

        Log::info("Загружаем файл по адресу: $url (offset=$offset, total=$totalSize)");

        $fh = fopen($tmpPath, $offset ? 'ab' : 'wb');

        $maxAttempts = 3;
        $attempt = 0;
        $downloaded = $offset;

        while ($downloaded < $totalSize && $attempt < $maxAttempts) {
            try {
                $response = Http::withHeaders([
                    'Range' => "bytes={$downloaded}-"
                ])
                    ->withOptions(['stream' => true])
                    ->timeout(30)
                    ->get($url);

                $body = $response->getBody();
                while (!$body->eof()) {
                    $chunk = $body->read(8192);
                    if ($chunk === '') {
                        throw new \RuntimeException("Пустой chunk в потоке");
                    }

                    fwrite($fh, $chunk);
                    $downloaded += strlen($chunk);

                    $import->update(['downloaded_bytes' => $downloaded]);
                }

                break; // успешная загрузка — выходим
            } catch (\Throwable $e) {
                $attempt++;
                Log::warning("Попытка $attempt: ошибка скачивания — " . $e->getMessage());
                sleep(1); // пауза перед повтором
            }
        }

        fclose($fh);

        // Проверяем результат
        if ($downloaded < $totalSize) {
            throw new \RuntimeException("Файл не скачан полностью: $downloaded / $totalSize");
        }

        // Переименовываем tmp → xml
        rename($tmpPath, $finalPath);
        Log::info(">>> Загрузка завершена. Файл сохранён: $finalPath");

        // Отправляем парсинг
        Log::info("Отправляем ParseXmlJob с importId = {$this->importId}");
        dispatch(new \App\Jobs\ParseXmlJob($this->importId));
    }

    protected function getTotalSize(string $url): ?int
    {
        $headers = get_headers($url, 1);
        if (is_array($headers) && isset($headers['Content-Length'])) {
            return (int) $headers['Content-Length'];
        }

        return null;
    }
}
