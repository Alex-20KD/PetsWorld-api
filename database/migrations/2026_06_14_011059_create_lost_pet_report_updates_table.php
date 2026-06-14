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
        Schema::create('lost_pet_report_updates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('report_id');
            $table->uuid('user_id');
            $table->enum('old_status', ['active', 'found', 'cancelled', 'expired'])->nullable();
            $table->enum('new_status', ['active', 'found', 'cancelled', 'expired']);
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('lost_pet_report_updates');
    }
};
