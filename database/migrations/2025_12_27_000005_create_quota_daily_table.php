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
        Schema::create('quota_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');

            // FAST tier (daily safety cap)
            $table->bigInteger('fast_tokens')->default(0);
            $table->integer('fast_requests')->default(0);

            // DEEP tier (daily safety cap)
            $table->bigInteger('deep_tokens')->default(0);
            $table->integer('deep_requests')->default(0);

            // GRACE tier (only daily, no monthly)
            $table->bigInteger('grace_tokens')->default(0);
            $table->integer('grace_requests')->default(0);

            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quota_daily');
    }
};



