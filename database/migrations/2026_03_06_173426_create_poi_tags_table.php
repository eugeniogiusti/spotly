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
        Schema::create('poi_tags', function (Blueprint $table) {
            $table->id();
            $table->string('poi_external_id', 100);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 50);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['poi_external_id', 'user_id', 'tag']);
            $table->index(['poi_external_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poi_tags');
    }
};
