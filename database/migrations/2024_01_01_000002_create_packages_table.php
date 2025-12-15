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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('disk_quota')->default(0)->comment('Bytes, 0 = unlimited');
            $table->unsignedBigInteger('bandwidth_quota')->default(0)->comment('Bytes/month');
            $table->unsignedInteger('max_domains')->default(0);
            $table->unsignedInteger('max_subdomains')->default(0);
            $table->unsignedInteger('max_email_accounts')->default(0);
            $table->unsignedInteger('max_email_forwarders')->default(0);
            $table->unsignedInteger('max_databases')->default(0);
            $table->unsignedInteger('max_ftp_accounts')->default(0);
            $table->unsignedInteger('max_parked_domains')->default(0);
            $table->json('features')->nullable()->comment('Feature flags');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_reseller_package')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
