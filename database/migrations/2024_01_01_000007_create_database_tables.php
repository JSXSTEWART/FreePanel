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
        Schema::create('databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('name', 64);
            $table->enum('type', ['mysql', 'postgresql'])->default('mysql');
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();

            $table->unique(['name', 'type']);
        });

        Schema::create('database_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('username', 32);
            $table->string('password_hash');
            $table->string('host')->default('localhost');
            $table->enum('type', ['mysql', 'postgresql'])->default('mysql');
            $table->timestamps();

            $table->unique(['username', 'host', 'type']);
        });

        Schema::create('database_user_privileges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('database_id')->constrained()->onDelete('cascade');
            $table->foreignId('database_user_id')->constrained()->onDelete('cascade');
            $table->json('privileges')->comment('Array of privileges');
            $table->timestamps();

            $table->unique(['database_id', 'database_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('database_user_privileges');
        Schema::dropIfExists('database_users');
        Schema::dropIfExists('databases');
    }
};
