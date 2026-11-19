<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->longText('text');
            $table->enum('extraction_method', ['text', 'ocr', 'empty'])->default('text');
            $table->timestamps();

            $table->unique(['document_id', 'page_number']);
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('chunk_id', 150)->unique();
            $table->unsignedInteger('page_number');
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('char_count');
            $table->timestamps();

            $table->index(['document_id', 'page_number']);
        });

        Schema::create('extracted_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('type', 50)->index();
            $table->text('value');
            $table->unsignedInteger('page_number');
            $table->text('evidence')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracted_entities');
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('document_pages');
    }
};
