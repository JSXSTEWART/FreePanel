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
        Schema::table('users', function (Blueprint $table) {
            $table->string('oauth_provider')->nullable()->after('password');
            $table->string('oauth_provider_id')->nullable()->after('oauth_provider');
            $table->text('oauth_access_token')->nullable()->after('oauth_provider_id');
            $table->text('oauth_refresh_token')->nullable()->after('oauth_access_token');
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['oauth_provider', 'oauth_provider_id', 'oauth_access_token', 'oauth_refresh_token']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
