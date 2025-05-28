<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id(); // Оставим числовой ID для товаров
            $table->string('name');
            $table->boolean('available')->default(false);
            $table->decimal('price', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->text('description_format')->nullable();
            $table->string('category_id')->nullable();
            $table->string('vendor')->nullable();
            $table->string('vendor_code')->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->string('barcode')->nullable();
            $table->json('pictures')->nullable();
            $table->timestamps();

            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
