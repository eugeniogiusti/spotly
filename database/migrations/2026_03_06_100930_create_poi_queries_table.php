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
        Schema::create('poi_queries', function (Blueprint $table) {
            $table->id();
            $table->string('layer', 50);
            $table->decimal('south', 10, 7);
            $table->decimal('west', 10, 7);
            $table->decimal('north', 10, 7);
            $table->decimal('east', 10, 7);
            $table->timestamp('queried_at');

            $table->index(['layer', 'queried_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poi_queries');
    }
};
