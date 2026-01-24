<?php

use VelvetCMS\Database\Schema\Blueprint;
use VelvetCMS\Database\Schema\Schema;

return new class {
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('content');
            $table->string('status', 20)->default('draft')->index();
            $table->string('layout', 100)->nullable();
            $table->text('excerpt')->nullable();
            $table->text('meta')->nullable(); // JSON encoded metadata
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps(); // created_at, updated_at

            // Custom index for created_at since timestamps() doesn't add indexes
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
