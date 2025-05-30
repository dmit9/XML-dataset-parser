<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductFilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CatalogController extends Controller
{
    protected ProductFilterService $filterService;

    public function __construct(ProductFilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * Получить список товаров по фильтрам
     */
    public function products(Request $request): JsonResponse
    {
        try {
            $filters = $request->input('filter', []);
            $productIds = $this->filterService->getFilteredProductIds($filters);

            // Сортировка
            $query = Product::whereIn('id', $productIds);
            switch ($request->input('sort_by')) {
                case 'price_asc':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('price', 'desc');
                    break;
                default:
                    $query->orderBy('id', 'asc');
                    break;
            }

            // Пагинация
            $perPage = $request->input('limit', 10);
            $page = $request->input('page', 1);
            $products = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка загрузки каталога: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка получения товаров'], 500);
        }
    }

    /**
     * Получить список фильтров с count
     */
    public function filters(Request $request): JsonResponse
    {
        try {
            $filters = $request->input('filter', []);
            $result = $this->filterService->getFilterStats($filters);

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Ошибка фильтров: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка при получении фильтров'], 500);
        }
    }
}
