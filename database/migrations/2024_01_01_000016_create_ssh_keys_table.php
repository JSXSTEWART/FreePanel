<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssh_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('public_key');
            $table->string('fingerprint')->unique();
            $table->string('key_type')->default('ssh-rsa'); // ssh-rsa, ssh-ed25519, ecdsa-sha2-nistp256
            $table->integer('key_bits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'is_active']);
        });

        Schema::create('ssh_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('ssh_key_id')->nullable()->constrained()->onDelete('set null');
            $table->string('ip_address');
            $table->string('username');
            $table->enum('status', ['success', 'failed', 'blocked']);
            $table->string('failure_reason')->nullable();
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['account_id', 'logged_at']);
            $table->index(['ip_address', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssh_access_logs');
        Schema::dropIfExists('ssh_keys');
    }
};
