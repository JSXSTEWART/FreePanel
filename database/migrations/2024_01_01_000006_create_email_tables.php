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
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('local_part', 64)->comment('Part before @');
            $table->string('password_hash');
            $table->unsignedBigInteger('quota')->default(0)->comment('Bytes, 0 = unlimited');
            $table->unsignedBigInteger('quota_used')->default(0);
            $table->string('maildir_path');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['domain_id', 'local_part']);
        });

        Schema::create('email_forwarders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->onDelete('cascade');
            $table->string('source')->comment('user@domain or @domain (catch-all)');
            $table->text('destination')->comment('Comma-separated destinations');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('source');
        });

        Schema::create('email_autoresponders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->onDelete('cascade');
            $table->string('subject');
            $table->text('body');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_autoresponders');
        Schema::dropIfExists('email_forwarders');
        Schema::dropIfExists('email_accounts');
    }
};
