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
        Schema::create('zapier_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('event_type'); // account.created, domain.deleted, ssl.expiring, etc.
            $table->text('webhook_url'); // The Zapier webhook URL
            $table->enum('format', ['json', 'form-encoded', 'xml'])->default('json');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('failure_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['account_id', 'event_type']);
            $table->index(['is_active', 'event_type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zapier_webhooks');
    }
};
