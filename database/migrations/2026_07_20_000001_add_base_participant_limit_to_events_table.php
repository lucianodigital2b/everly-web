<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            // The event's participant capacity *before* any guest packs were
            // applied, captured the first time a pack is credited. Guest-pack
            // math recomputes participant_limit as base + Σ(applied packs), so
            // refunds reverse cleanly back to this. Null once captured means the
            // event was already unlimited. Null before capture is simply "no
            // packs bought yet", and participant_limit stands on its own.
            $table->integer('base_participant_limit')->nullable()->after('participant_limit');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('base_participant_limit');
        });
    }
};
