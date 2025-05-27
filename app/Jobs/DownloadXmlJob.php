<?php

namespace App\Jobs;

use App\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Throwable;
use GuzzleHttp\Client;
use Illuminate\Foundation\Bus\Dispatchable;

class DownloadXmlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $importId;

    public function __construct(int $importId)
    {
        $this->importId = $importId;
    }

    public function middleware(): array
    {
        return [new ThrottlesExceptions(5, 60)];
    }

    public function handle(): void
    {
        $import = Import::findOrFail($this->importId);

        $client = new Client();
        $offset = $import->downloaded_bytes ?? 0;

        $pathTmp = storage_path("app/imports/{$import->id}.xml.tmp");

        if (!Storage::exists("imports")) {
            Storage::makeDirectory("imports");
        }

        $headers = $offset > 0 ? ['Range' => "bytes={$offset}-"] : [];

        $response = $client->request('GET', $import->url, [
            'stream' => true,
            'headers' => $headers,
            'timeout' => 60,
        ]);

        $total = $import->total_bytes ?? $this->getTotalSize($response, $offset);
        $import->update(['total_bytes' => $total]);

        $file = fopen($pathTmp, $offset > 0 ? 'ab' : 'wb');
        $body = $response->getBody();

        $bytesWritten = 0;
        while (!$body->eof()) {
            $chunk = $body->read(1024 * 512); // 512 KB
            $bytes = fwrite($file, $chunk);
            $bytesWritten += $bytes;

            $import->increment('downloaded_bytes', $bytes);
        }

        fclose($file);

        if ($import->downloaded_bytes < $import->total_bytes) {
            throw new \RuntimeException("Incomplete download (got {$import->downloaded_bytes} of {$import->total_bytes})");
        }

        // Переносим в .xml
        Storage::move("imports/{$import->id}.xml.tmp", "imports/{$import->id}.xml");

        $import->update([
            'status' => 'parsing',
            'started_at' => now(),
        ]);

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
        Log::error("DownloadXmlJob failed: {$e->getMessage()}");
        Import::where('id', $this->importId)->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}
