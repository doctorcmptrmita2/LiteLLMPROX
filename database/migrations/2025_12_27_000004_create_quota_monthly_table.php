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
        Schema::create('quota_monthly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->char('month', 7); // YYYY-MM

            // FAST tier
            $table->bigInteger('fast_input_tokens')->default(0);
            $table->bigInteger('fast_output_tokens')->default(0);
            $table->integer('fast_requests')->default(0);

            // DEEP tier
            $table->bigInteger('deep_input_tokens')->default(0);
            $table->bigInteger('deep_output_tokens')->default(0);
            $table->integer('deep_requests')->default(0);

            // PLANNER overhead tracking
            $table->bigInteger('planner_tokens')->default(0);

            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quota_monthly');
    }
};

