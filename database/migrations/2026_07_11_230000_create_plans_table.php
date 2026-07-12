<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('brl');

            // 0 means unlimited, matching the "0 / null = ∞" note in the spec.
            $table->unsignedInteger('max_uploads')->nullable();
            $table->unsignedInteger('max_participants')->nullable();

            $table->boolean('allow_download')->default(false);
            $table->boolean('allow_slideshow')->default(false);
            $table->boolean('white_label')->default(false);
            $table->unsignedInteger('duration_days')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
