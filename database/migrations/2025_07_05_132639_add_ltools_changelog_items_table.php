<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('ltools.table_name_changelog_items'), function (Blueprint $table) {
            $table->id();
            // Stores a model FQCN — keep it a VARCHAR so it can be indexed.
            // MySQL cannot index TEXT/BLOB without a key length (error 1170),
            // whereas PostgreSQL can; VARCHAR(255) works on both.
            $table->string('model');
            $table->string('model_id');
            $table->json('changes');
            $table->bigInteger('user_id')->nullable();
            $table->uuid()->unique('IDX_uuid');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['model', 'model_id'], 'IDX_model__model_id');
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
