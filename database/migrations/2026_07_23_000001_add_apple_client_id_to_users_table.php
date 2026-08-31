<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Which bundle id the Apple credential was issued to — production,
            // .dev or .preview. Apple's revoke endpoint demands the same
            // client_id the refresh token was minted under, so guessing the
            // production one would fail for anybody who signed in on a
            // non-production build.
            $table->string('apple_client_id')->nullable()->after('apple_refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('apple_client_id');
        });
    }
};
