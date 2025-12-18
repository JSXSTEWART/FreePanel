<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('git_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('path'); // Path in user's home directory
            $table->string('clone_url')->nullable();
            $table->string('branch')->default('main');
            $table->string('deploy_path')->nullable(); // Where to deploy on push
            $table->boolean('auto_deploy')->default(false);
            $table->text('deploy_script')->nullable(); // Custom deploy script
            $table->string('last_commit_hash')->nullable();
            $table->string('last_commit_message')->nullable();
            $table->timestamp('last_push_at')->nullable();
            $table->boolean('is_private')->default(true);
            $table->timestamps();

            $table->unique(['account_id', 'name']);
            $table->index(['account_id', 'path']);
        });

        Schema::create('git_deploy_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained('git_repositories')->onDelete('cascade');
            $table->string('name');
            $table->text('public_key');
            $table->string('fingerprint');
            $table->boolean('write_access')->default(false);
            $table->timestamps();

            $table->unique(['repository_id', 'fingerprint']);
        });

        Schema::create('git_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained('git_repositories')->onDelete('cascade');
            $table->string('url');
            $table->string('secret')->nullable();
            $table->json('events')->default('["push"]'); // Events to trigger webhook
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->string('last_response_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('git_webhooks');
        Schema::dropIfExists('git_deploy_keys');
        Schema::dropIfExists('git_repositories');
    }
};
