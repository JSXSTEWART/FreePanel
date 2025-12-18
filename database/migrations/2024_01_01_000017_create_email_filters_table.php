<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('email_account')->nullable(); // null = apply to all accounts
            $table->string('name');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('conditions'); // [{"field": "from", "match": "contains", "value": "spam"}]
            $table->json('actions'); // [{"action": "move", "destination": "Junk"}]
            $table->boolean('stop_processing')->default(false);
            $table->timestamps();

            $table->index(['account_id', 'email_account', 'is_active']);
            $table->index(['account_id', 'priority']);
        });

        Schema::create('spam_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->boolean('spam_filter_enabled')->default(true);
            $table->integer('spam_threshold')->default(5); // SpamAssassin score threshold
            $table->boolean('auto_delete_spam')->default(false);
            $table->integer('auto_delete_score')->default(10);
            $table->boolean('spam_box_enabled')->default(true);
            $table->json('whitelist')->nullable(); // List of whitelisted addresses
            $table->json('blacklist')->nullable(); // List of blacklisted addresses
            $table->timestamps();

            $table->unique('account_id');
        });

        Schema::create('email_forwarders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('source_email');
            $table->string('destination_email');
            $table->boolean('keep_copy')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['account_id', 'source_email', 'destination_email']);
        });

        Schema::create('autoresponders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_html')->default(false);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->integer('interval_hours')->default(24); // Min hours between responses to same sender
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['account_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autoresponders');
        Schema::dropIfExists('email_forwarders');
        Schema::dropIfExists('spam_settings');
        Schema::dropIfExists('email_filters');
    }
};
