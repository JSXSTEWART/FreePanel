<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // IP Blocker
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('ip_address', 45); // IPv6 compatible
            $table->text('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'ip_address']);
            $table->index(['account_id', 'expires_at']);
        });

        // Hotlink Protection
        Schema::create('hotlink_protection', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->boolean('is_enabled')->default(false);
            $table->text('allowed_urls')->nullable(); // JSON array
            $table->text('protected_extensions')->nullable(); // JSON array
            $table->boolean('allow_direct_requests')->default(true);
            $table->string('redirect_url')->nullable();
            $table->timestamps();

            $table->unique('account_id');
        });

        // Password Protected Directories
        Schema::create('protected_directories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('path');
            $table->string('name');
            $table->timestamps();

            $table->unique(['account_id', 'path']);
        });

        Schema::create('protected_directory_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protected_directory_id')->constrained()->onDelete('cascade');
            $table->string('username');
            $table->string('password'); // htpasswd hashed
            $table->timestamps();

            $table->unique(['protected_directory_id', 'username']);
        });

        // Custom Error Pages
        Schema::create('error_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('domain_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('error_code'); // 400, 401, 403, 404, 500, etc.
            $table->text('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['account_id', 'domain_id', 'error_code']);
        });

        // MIME Types
        Schema::create('mime_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('extension', 50);
            $table->string('mime_type');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['account_id', 'extension']);
        });

        // Redirects
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('domain_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('source_path');
            $table->string('destination_url');
            $table->enum('type', ['permanent', 'temporary'])->default('permanent'); // 301 or 302
            $table->boolean('wildcard')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['account_id', 'domain_id']);
        });

        // Leech Protection (prevent password sharing)
        Schema::create('leech_protection', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('path');
            $table->integer('max_logins')->default(2);
            $table->string('redirect_url')->nullable();
            $table->boolean('send_email')->default(true);
            $table->boolean('disable_account')->default(false);
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->unique(['account_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leech_protection');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('mime_types');
        Schema::dropIfExists('error_pages');
        Schema::dropIfExists('protected_directory_users');
        Schema::dropIfExists('protected_directories');
        Schema::dropIfExists('hotlink_protection');
        Schema::dropIfExists('blocked_ips');
    }
};
