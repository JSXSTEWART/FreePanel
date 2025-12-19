<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('domain_id')->nullable()->constrained()->onDelete('set null');
            $table->string('name');
            $table->enum('type', ['nodejs', 'python', 'ruby', 'php']);
            $table->string('runtime_version'); // e.g., "18.x" for Node, "3.11" for Python
            $table->string('path'); // Application root path
            $table->string('entry_point')->default('app.js'); // Main file
            $table->string('startup_file')->nullable(); // Custom startup script
            $table->integer('port'); // Internal port the app runs on
            $table->json('environment_variables')->nullable();
            $table->enum('status', ['stopped', 'starting', 'running', 'error'])->default('stopped');
            $table->string('process_manager')->default('pm2'); // pm2, passenger, gunicorn, etc.
            $table->integer('instances')->default(1);
            $table->integer('max_memory_mb')->nullable();
            $table->boolean('auto_restart')->default(true);
            $table->boolean('watch_files')->default(false);
            $table->string('log_path')->nullable();
            $table->string('error_log_path')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'name']);
            $table->unique(['account_id', 'port']);
            $table->index(['account_id', 'status']);
        });

        Schema::create('application_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['stdout', 'stderr', 'system']);
            $table->text('message');
            $table->timestamp('logged_at');

            $table->index(['application_id', 'logged_at']);
        });

        Schema::create('application_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->float('cpu_usage')->nullable();
            $table->bigInteger('memory_usage')->nullable();
            $table->integer('active_handles')->nullable();
            $table->integer('requests_per_minute')->nullable();
            $table->float('avg_response_time_ms')->nullable();
            $table->timestamp('recorded_at');

            $table->index(['application_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_metrics');
        Schema::dropIfExists('application_logs');
        Schema::dropIfExists('applications');
    }
};
