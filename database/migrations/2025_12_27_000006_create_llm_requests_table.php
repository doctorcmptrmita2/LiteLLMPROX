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
        Schema::create('llm_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('api_key_id')->nullable();

            // Decompose tracking
            $table->uuid('parent_request_id')->nullable(); // For decompose chunks
            $table->tinyInteger('chunk_index')->nullable(); // 0=planner, 1/2/3=chunks

            // Request identification
            $table->string('request_id', 64); // X-Request-Id
            $table->enum('tier', ['fast', 'deep', 'planner', 'grace']);
            $table->string('model_alias', 50)->nullable(); // cf-fast, cf-deep, etc.

            // Token usage
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);

            // Cost
            $table->decimal('cost_usd', 10, 6)->nullable();

            // Performance
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('time_to_first_token_ms')->nullable();

            // Flags
            $table->boolean('is_cached')->default(false);
            $table->boolean('is_streaming')->default(false);
            $table->boolean('is_decomposed')->default(false);

            // Status
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('error_type', 50)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index(['project_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('parent_request_id');
            $table->index('tier');
            $table->index('error_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_requests');
    }
};



