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
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('name', 253)->unique();
            $table->enum('type', ['main', 'addon', 'alias', 'parked'])->default('addon');
            $table->string('document_root');
            $table->boolean('is_active')->default(true);
            $table->boolean('ssl_enabled')->default(false);
            $table->string('php_version', 10)->default('8.2');
            $table->timestamps();

            $table->index('account_id');
        });

        Schema::create('subdomains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('name', 63)->comment('Subdomain prefix');
            $table->string('document_root');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['domain_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subdomains');
        Schema::dropIfExists('domains');
    }
};
