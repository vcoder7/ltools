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
        Schema::create(config('ltools.table_name_changelog_items'), function (Blueprint $table) {
            $table->id();
            $table->string('model_id');
            $table->text('model')->index('IDX_model');
            $table->json('changes');
            $table->bigInteger('user_id')->nullable();
            $table->uuid()->unique('UQ_uuid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ltools.table_name_changelog_items'));
    }
};
