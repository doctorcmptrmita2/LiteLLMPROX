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
        Schema::create('usage_daily_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->date('date');

            // FAST tier breakdown
            $table->bigInteger('fast_tokens')->default(0);
            $table->integer('fast_requests')->default(0);
            $table->decimal('fast_cost_usd', 10, 4)->default(0);

            // DEEP tier breakdown
            $table->bigInteger('deep_tokens')->default(0);
            $table->integer('deep_requests')->default(0);
            $table->decimal('deep_cost_usd', 10, 4)->default(0);

            // GRACE tier breakdown
            $table->bigInteger('grace_tokens')->default(0);
            $table->integer('grace_requests')->default(0);
            $table->decimal('grace_cost_usd', 10, 4)->default(0);

            // PLANNER breakdown
            $table->bigInteger('planner_tokens')->default(0);
            $table->integer('planner_requests')->default(0);

            // Totals
            $table->bigInteger('total_tokens')->default(0);
            $table->integer('total_requests')->default(0);
            $table->decimal('total_cost_usd', 10, 4)->default(0);

            // Cache stats
            $table->integer('cache_hits')->default(0);

            // Decompose stats
            $table->integer('decomposed_requests')->default(0);

            $table->timestamps();

            $table->unique(['project_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_daily_aggregates');
    }
};



