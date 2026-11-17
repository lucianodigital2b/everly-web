<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            // Set when the host removes a guest's photos and blocks them. The
            // guest token keeps working for reads, but no upload is accepted.
            $table->timestamp('blocked_at')->nullable()->after('upload_count');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->dropColumn('blocked_at');
        });
    }
};
