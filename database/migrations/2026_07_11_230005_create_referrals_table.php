<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();

            // Null until someone redeems the code.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->json('reward')->nullable();
            $table->dateTime('redeemed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
