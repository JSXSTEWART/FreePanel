<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->onDelete('cascade');
            $table->boolean('is_system')->default(false); // System-wide backup
            $table->enum('type', ['full', 'files', 'databases', 'emails'])->default('full');
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->string('day_of_week')->nullable(); // 0-6 for weekly
            $table->integer('day_of_month')->nullable(); // 1-31 for monthly
            $table->string('time', 5)->default('02:00'); // HH:MM
            $table->integer('retention_days')->default(30);
            $table->string('destination')->default('local'); // local, remote, s3
            $table->json('destination_config')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_run')->nullable();
            $table->string('last_status')->nullable();
            $table->timestamps();

            $table->index(['is_enabled', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};
