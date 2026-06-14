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
        Schema::create('lost_pet_reports', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_id');
            $table->uuid('pet_id')->nullable();
            $table->string('pet_name')->nullable();
            $table->string('species', 50)->nullable();
            $table->string('breed')->nullable();
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->string('photo_url')->nullable();
            $table->enum('status', ['active', 'found', 'cancelled', 'expired'])->default('active');
            $table->boolean('is_found')->default(false);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('location_description')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestampTz('lost_at')->nullable();
            $table->timestampTz('found_at')->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete();

            $table->foreign('pet_id')
                  ->references('id')
                  ->on('pets')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lost_pet_reports');
    }
};
