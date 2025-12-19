<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('minute', 20)->default('*');
            $table->string('hour', 20)->default('*');
            $table->string('day', 20)->default('*');
            $table->string('month', 20)->default('*');
            $table->string('weekday', 20)->default('*');
            $table->text('command');
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run')->nullable();
            $table->string('last_status')->nullable();
            $table->text('last_output')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_jobs');
    }
};
