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
        Schema::create('pets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('owner_id')->nullable();
            $table->string('name');
            $table->enum('species', ['dog', 'cat', 'bird', 'rabbit', 'other']);
            $table->string('breed')->nullable();
            $table->integer('age')->nullable();
            $table->string('size')->nullable();
            $table->string('gender')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->integer('views')->default(0);
            $table->enum('status', ['available', 'adopted', 'fostered', 'pending'])->default('available');
            $table->boolean('is_approved')->default(false);
            $table->timestampTz('adoption_date')->nullable();
            $table->timestampsTz();

            $table->foreign('owner_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pets');
    }
};
