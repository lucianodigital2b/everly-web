<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_pack_purchases', function (Blueprint $table): void {
            $table->id();

            // Nullable + null-on-delete so the audit row survives even if the
            // event or user is later removed. A row with a null event_id is one
            // we could not match to an event (see `status`).
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // RevenueCat's per-delivery event id. Unique so webhook retries of
            // the same event are deduped. Null for client-driven sync rows.
            $table->string('rc_event_id')->nullable()->unique();

            // Store transaction id. A purchase and its later refund share this,
            // which is how a REFUND finds the purchase it reverses.
            $table->string('transaction_id')->nullable()->index();

            // Webhook event type, e.g. NON_RENEWING_PURCHASE, REFUND.
            $table->string('type');

            // The purchased product / SKU.
            $table->string('product_id')->nullable();

            // Participants this row credits: positive for a purchase, negative
            // for a refund's reversal, null when the pack grants unlimited.
            $table->integer('participants_delta')->nullable();
            $table->boolean('grants_unlimited')->default(false);

            // applied | reversed | reversal | ignored | unmatched — see model.
            $table->string('status');

            // webhook | sync — which path recorded this row.
            $table->string('source');

            // Full untouched payload, for audit and replay.
            $table->json('raw');

            $table->timestamps();

            $table->index(['transaction_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_pack_purchases');
    }
};
