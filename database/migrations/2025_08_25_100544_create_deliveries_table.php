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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('endpoint_id')->constrained()->onDelete('cascade');
            $table->json('payload');
            $table->enum('status', ['pending', 'success', 'failed', 'retrying'])->default('pending');
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();
            
            $table->index(['event_id', 'status']);
            $table->index(['endpoint_id', 'status']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
