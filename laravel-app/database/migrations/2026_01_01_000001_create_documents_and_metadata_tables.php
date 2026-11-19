<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 100)->default('Other');
            $table->decimal('category_confidence', 5, 4)->default(0.0000);
            $table->enum('category_source', ['pending', 'ai', 'manual'])->default('pending');
            $table->json('tags')->nullable();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('page_count')->default(0);
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->index();
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['organization_id', 'department_id']);
        });

        Schema::create('document_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained('documents')->cascadeOnDelete();
            $table->string('issuing_organization')->nullable();
            $table->string('reference_number')->nullable();
            $table->date('document_date')->nullable();
            $table->string('project_name')->nullable();
            $table->string('budget', 100)->nullable();
            $table->string('deadline', 100)->nullable();
            $table->json('keywords')->nullable();
            $table->json('summary')->nullable();
            $table->timestamp('summary_generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_metadata');
        Schema::dropIfExists('documents');
    }
};
