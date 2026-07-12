<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();

            // Guest upload URLs are built from this, so it must be unguessable.
            $table->string('qr_code_token', 32)->unique();

            $table->enum('status', ['active', 'pending_payment'])->default('pending_payment');
            $table->boolean('public')->default(false);

            $table->string('name');
            $table->string('title')->nullable();
            $table->dateTime('event_date')->nullable();

            $table->unsignedInteger('shot_limit')->nullable();
            $table->unsignedInteger('participant_limit')->nullable();

            $table->enum('reveal_time', ['Instant', 'End of Event', 'Scheduled'])->default('Instant');
            $table->dateTime('reveal_at')->nullable();

            $table->enum('filter', ['none', 'warm', 'film', 'mono'])->default('none');
            $table->string('tier')->nullable();

            $table->boolean('is_revealed')->default(false);
            $table->string('cover_image_url')->nullable();

            $table->dateTime('activated_at')->nullable();
            $table->dateTime('expires_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
