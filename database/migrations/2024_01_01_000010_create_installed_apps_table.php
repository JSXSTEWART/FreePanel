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
        Schema::create('installed_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('app_type', 50)->comment('wordpress, joomla, etc.');
            $table->string('version', 20);
            $table->string('install_path');
            $table->string('admin_url')->nullable();
            $table->string('admin_username')->nullable();
            $table->foreignId('database_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('auto_update')->default(false);
            $table->timestamp('installed_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installed_apps');
    }
};
