<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Models\Product;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CatalogImportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'url' => 'required|url'
            ]);

            $import = app(ImportService::class)->createImport($validated['url']);

            return response()->json(['id' => $import->id], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 400);
        } catch (\Throwable $e) {
            Log::error('Ошибка импорта: ' . $e->getMessage());
            return response()->json([
                'message' => 'Серверная ошибка при запуске импорта'
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $import = Import::findOrFail($id);
        return response()->json($import);
    }

    public function products(int $id): JsonResponse
    {
        try {
            $import = Import::findOrFail($id);
            return response()->json($import);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Импорт не найден'], 404);
        } catch (\Throwable $e) {
            Log::error('Ошибка получения статуса импорта: ' . $e->getMessage());
            return response()->json(['message' => 'Внутренняя ошибка сервера'], 500);
        }
    }
}
