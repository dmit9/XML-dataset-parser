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
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->enum('status', ['downloading', 'parsing', 'completed', 'failed'])->default('downloading');
            $table->unsignedBigInteger('total_bytes')->nullable();
            $table->unsignedBigInteger('downloaded_bytes')->default(0);
            $table->unsignedInteger('parsed_offers')->default(0);
            $table->unsignedInteger('total_offers')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
