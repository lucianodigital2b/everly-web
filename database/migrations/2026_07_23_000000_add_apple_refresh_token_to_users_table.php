<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Obtained by exchanging the one-time `authorization_code` the app
            // forwards from Sign in with Apple. Kept solely so account deletion
            // can call Apple's revoke endpoint, which App Store guideline
            // 5.1.1(v) requires. Encrypted at rest — it grants ongoing access to
            // the user's Apple identity. Text, not string: the token is long and
            // Apple documents no maximum length.
            $table->text('apple_refresh_token')->nullable()->after('apple_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('apple_refresh_token');
        });
    }
};
