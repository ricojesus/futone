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
        Schema::dropIfExists('cities');
    }

    public function down(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('state', 10)->nullable();
            $table->foreignUuid('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->timestamps();
            $table->unique(['name', 'state', 'country_id']);
        });
    }
};
