<?php

namespace App\app\Services;

use App\Jobs\DownloadXmlJob;
use App\Models\Import;

class ImportService
{
    public function createImport(string $url): Import
    {
        $import = Import::create([
            'url' => $url,
            'status' => 'downloading',
        ]);

        DownloadXmlJob::dispatch($import->id);
        return $import;
    }
}
