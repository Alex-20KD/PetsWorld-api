<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pet_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('report_id');
            $table->uuid('user_id');
            $table->decimal('alert_latitude', 10, 8)->nullable();
            $table->decimal('alert_longitude', 11, 8)->nullable();
            $table->integer('radius_km')->default(5);
            $table->boolean('is_read')->default(false);
            $table->boolean('is_sent')->default(false);
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->foreign('report_id')
                  ->references('id')
                  ->on('lost_pet_reports')
                  ->cascadeOnDelete();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pet_alerts');
    }
};
