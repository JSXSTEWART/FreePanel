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
        Schema::create('dns_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('serial')->default(1);
            $table->unsignedInteger('refresh')->default(10800);
            $table->unsignedInteger('retry')->default(3600);
            $table->unsignedInteger('expire')->default(604800);
            $table->unsignedInteger('minimum')->default(86400);
            $table->unsignedInteger('ttl')->default(86400);
            $table->string('primary_ns', 253)->nullable();
            $table->string('admin_email', 253)->nullable();
            $table->timestamps();

            $table->unique('domain_id');
        });

        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('dns_zones')->onDelete('cascade');
            $table->string('name', 253);
            $table->enum('type', ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'PTR']);
            $table->text('content');
            $table->unsignedInteger('ttl')->default(86400);
            $table->unsignedInteger('priority')->nullable()->comment('For MX, SRV');
            $table->boolean('is_system')->default(false)->comment('System-managed records');
            $table->timestamps();

            $table->index(['zone_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dns_records');
        Schema::dropIfExists('dns_zones');
    }
};
