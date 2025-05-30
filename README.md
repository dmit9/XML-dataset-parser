# 🛍️ Laravel Catalog with XML Import & Dynamic Filters

> Каталог товаров с импором из XML, хранением в MySQL, Redis-фильтрацией и фронтом на Vue 3.  
> Реализован как тестовое задание: упор на производительность, масштабируемость и чистый API.

---

![PHP](https://img.shields.io/badge/php-8.3-blue?logo=php)
![Laravel](https://img.shields.io/badge/laravel-12-red?logo=laravel)
![Vue](https://img.shields.io/badge/vue-3-green?logo=vue.js)
![Redis](https://img.shields.io/badge/redis-7-orange?logo=redis)
![License](https://img.shields.io/badge/license-MIT-lightgrey)
![Status](https://img.shields.io/badge/status-Complete-brightgreen)

---

## 🚀 Stack

- 💡 Laravel 12 (PHP 8.3)
- 🛢️ MySQL (или MariaDB)
- ⚡ Redis (множества, кэш фильтров)
- ⏱️ Laravel Queue + Supervisor
- 🎛️ XMLReader + SimpleXML
- 🖥️ Vue 3 + Composition API
- 📦 OpenAPI (Swagger) спецификация

---

## ⚙️ Установка

```bash
git clone https://github.com/your-name/catalog-task.git
cd catalog-task

cp .env.example .env
composer install
php artisan key:generate

php artisan migrate
npm install && npm run build

# очередь
php artisan queue:work


📤 Импорт XML
POST /api/imports

Body: { "url": "https://example.com/DataSet.xml" }

скачивание XML

парсинг с валидацией

сохранение товаров, категорий, параметров

построение фильтров в Redis

Прогресс можно отслеживать через: GET /api/imports/{id}

🔍 Фильтрация и API

GET /api/catalog/products
GET /api/catalog/filters

Параметры фильтрации:
?sort_by=price_asc
&filter[brand][]=Apple
&filter[color][]=Черный

Счётчики фильтров (count) динамически рассчитываются через Redis-пересечения (sinter, sintercard).

🛠️ Обработка ошибок
400 Bad Request — ошибка валидации

404 Not Found — не найдено

500 Server Error — логируется в laravel.log

🖼️ Vue 3 фронт (в одной странице)
импорт XML по URL

отображение прогресса загрузки

мультиселект-фильтры с подсчётом

пагинация, сортировка

сброс фильтров

Смотри: resources/js/App.vue

📘 OpenAPI документация
📄 docs/openapi.yaml — спецификация OpenAPI 3.0
Импортируй в Swagger Editor, Postman, Insomnia.

✅ Выполнено
 Импорт XML из внешнего URL

 Обработка больших файлов

 Валидация полей id, name, price

 MySQL-модель: товары, категории, параметры

 Redis: динамические фильтры через множества

 REST API /products, /filters, /imports

 Vue-фронт с фильтрами и сортировкой

 Обработка ошибок и статус-коды

 Документация OpenAPI

 Supervisor + очередь




