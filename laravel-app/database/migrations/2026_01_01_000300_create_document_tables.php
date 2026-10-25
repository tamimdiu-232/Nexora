<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique(); // public identifier used in URLs and sent to the AI service
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('category', 60)->default('Other')->index();
            $t->float('category_confidence')->nullable();
            $t->string('category_source', 10)->default('pending'); // pending | ai | manual
            $t->string('tags')->nullable();
            $t->string('original_name');
            $t->string('stored_path'); // private disk path, never exposed
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('size_bytes');
            $t->char('sha256', 64);
            $t->unsignedInteger('page_count')->nullable();
            $t->string('processing_status', 20)->default('pending')->index(); // pending|processing|completed|failed
            $t->string('processing_error')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->softDeletes();
            $t->timestamps();
            $t->index(['organization_id', 'department_id']);
        });

        Schema::create('document_metadata', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('issuing_organization')->nullable();
            $t->string('reference_number')->nullable()->index();
            $t->string('document_date', 50)->nullable();
            $t->string('project_name')->nullable();
            $t->string('budget', 100)->nullable();
            $t->string('deadline', 100)->nullable();
            $t->json('keywords')->nullable();
            $t->json('summary')->nullable();
            $t->timestamp('summary_generated_at')->nullable();
            $t->timestamps();
        });

        Schema::create('document_pages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('page_number');
            $t->longText('text');
            $t->string('extraction_method', 10); // text | ocr | empty
            $t->timestamps();
            $t->unique(['document_id', 'page_number']);
        });

        // References to vector records held by the AI service (vectors themselves are not stored in MySQL).
        Schema::create('document_chunks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->string('chunk_id', 150);
            $t->unsignedInteger('page_number');
            $t->unsignedInteger('chunk_index');
            $t->unsignedInteger('char_count');
            $t->timestamps();
            $t->unique(['document_id', 'chunk_id']);
        });

        Schema::create('processing_jobs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20)->index();
            $t->unsignedInteger('attempt')->default(1);
            $t->string('error_code', 60)->nullable();
            $t->string('error_message')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });

        Schema::create('extracted_entities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->string('type', 40)->index();
            $t->string('value', 500);
            $t->unsignedInteger('page_number')->nullable();
            $t->text('evidence')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['extracted_entities', 'processing_jobs', 'document_chunks', 'document_pages', 'document_metadata', 'documents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
