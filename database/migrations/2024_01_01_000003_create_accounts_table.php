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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('package_id')->constrained();
            $table->string('username', 16)->unique()->comment('System username');
            $table->string('domain', 253)->comment('Primary domain');
            $table->string('home_directory');
            $table->string('shell', 100)->default('/bin/bash');
            $table->unsignedInteger('uid')->nullable();
            $table->unsignedInteger('gid')->nullable();
            $table->unsignedBigInteger('disk_used')->default(0);
            $table->unsignedBigInteger('bandwidth_used')->default(0);
            $table->text('suspend_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('user_id');
        });

        // Reseller configuration
        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('max_accounts')->default(0);
            $table->unsignedBigInteger('disk_limit')->default(0);
            $table->unsignedBigInteger('bandwidth_limit')->default(0);
            $table->json('nameservers')->nullable();
            $table->json('branding')->nullable();
            $table->json('allowed_packages')->nullable()->comment('Package IDs they can assign');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resellers');
        Schema::dropIfExists('accounts');
    }
};
