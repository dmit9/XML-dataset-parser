<?php

namespace App\Services;

use App\Models\Parameter;
use Illuminate\Support\Facades\Redis;

class ProductFilterService
{
    /**
     * Получить список product_id, удовлетворяющих всем активным фильтрам
     */
    public function getFilteredProductIds(array $filters): array
    {
        $sets = [];

        foreach ($filters as $slug => $values) {
            $values = is_array($values) ? $values : [$values];

            $slugs = array_map(function ($v) use ($slug) {
                return "filter:{$slug}:{$v}";
            }, $values);

            // объединяем все значения одного фильтра (OR)
            if (count($slugs) === 1) {
                $sets[] = $slugs[0];
            } else {
                $tempKey = "tmp:" . md5(implode('|', $slugs));
                Redis::connection()->client()->sInterStore($tempKey, $keys);
                Redis::connection()->client()->expire($tempKey, 30);
                $sets[] = $tempKey;
            }
        }

        if (empty($sets)) {
            // если нет фильтров — все товары
            return Redis::smembers('all_product_ids');
        }

        // пересекаем фильтры (AND)
        $result = Redis::sinter($sets);
        return $result ?: [];
    }

    /**
     * Вернуть структуру всех фильтров и count для каждого значения
     */
    public function getFilterStats(array $activeFilters): array
    {
        $allParameters = Parameter::with('values')->get();
        $result = [];

        foreach ($allParameters as $parameter) {
            $slug = $parameter->slug;
            $values = [];

            foreach ($parameter->values as $value) {
                $isActive = isset($activeFilters[$slug]) &&
                    in_array($value->value, (array) $activeFilters[$slug]);

                $filtersToUse = $activeFilters;
                if ($isActive) {
                    unset($filtersToUse[$slug]);
                }

                $tempSet = "filter:{$slug}:{$value->value}";
                $interKeys = array_merge([$tempSet], $this->toTempSets($filtersToUse));
                $tmpKey = "tmp:count:{$slug}:" . md5($value->value);

                $count = Redis::connection()->client()->sInterStore($tmpKey, ...$interKeys);
                Redis::connection()->client()->expire($tmpKey, 30);

                $values[] = [
                    'value' => $value->value,
                    'count' => $count,
                    'active' => $isActive,
                ];
            }


            $result[] = [
                'name' => $parameter->name,
                'slug' => $slug,
                'values' => $values,
            ];
        }

        return $result;
    }

    /**
     * Вспомогательная функция для временных множеств Redis
     */
    protected function toTempSets(array $filters): array
    {
        $sets = [];

        foreach ($filters as $slug => $values) {
            $values = is_array($values) ? $values : [$values];

            $keys = array_map(fn($val) => "filter:{$slug}:{$val}", $values);

            if (count($keys) === 1) {
                $sets[] = $keys[0];
            } else {
                $tempKey = "tmp:filter:{$slug}:" . md5(implode('|', $keys));
                Redis::connection()->client()->sInterStore($tempKey, ...$keys);
                Redis::connection()->client()->expire($tempKey, 30);
                $sets[] = $tempKey;
            }
        }

        return $sets;
    }
}
