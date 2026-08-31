<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            // The credit printed under each photo in the gallery. Nullable: a
            // guest can upload without ever naming themselves, and the client
            // then falls back to a plain "Guest".
            $table->string('name', 40)->nullable()->after('guest_token');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
