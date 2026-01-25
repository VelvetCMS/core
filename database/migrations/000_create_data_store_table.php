<?php

use VelvetCMS\Database\Schema\Blueprint;
use VelvetCMS\Database\Schema\Schema;

return new class {
    public function up(): void
    {
        Schema::create('data_store', function (Blueprint $table) {
            $table->id();
            $table->string('collection', 100);
            $table->string('key', 255);
            $table->text('data');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['collection', 'key']);
            $table->index('collection');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_store');
    }
};
