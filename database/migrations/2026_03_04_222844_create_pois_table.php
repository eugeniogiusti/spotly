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
        Schema::create('pois', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique(); // "osm:node:123456"
            $table->string('source')->default('overpass');
            $table->string('layer');
            $table->string('name')->default('');
            $table->double('lat');
            $table->double('lng');
            $table->json('raw_data');
            $table->timestamp('cached_at');
            $table->timestamps();

            // Speed up bbox + layer queries
            $table->index(['layer', 'lat', 'lng']);
            $table->index('cached_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pois');
    }
};
