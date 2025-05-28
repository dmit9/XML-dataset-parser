<?php

namespace App\Http\Controllers;

use App\app\Services\ImportService;
use App\Models\Import;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogImportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate(['url' => 'required|url']);
        $import = app(ImportService::class)->createImport($request->url);
        return response()->json(['id' => $import->id]);
    }

    public function show(int $id): JsonResponse
    {
        $import = Import::findOrFail($id);
        return response()->json($import);
    }

    public function products(int $id): JsonResponse
    {
        $import = Import::findOrFail($id);

        // Защитимся: отдаём только после завершения импорта
        if ($import->status !== 'completed') {
            return response()->json(['message' => 'Импорт не завершён'], 400);
        }

        $products = Product::latest()->take(100)->get(); // или фильтр по дате
        return response()->json($products);
    }
}
