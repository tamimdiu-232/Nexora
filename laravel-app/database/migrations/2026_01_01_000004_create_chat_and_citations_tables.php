<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->longText('content');
            $table->enum('status', ['answered', 'insufficient_evidence', 'conflicting_evidence', 'error'])->default('answered');
            $table->string('generated_by', 50)->default('extractive');
            $table->timestamps();
        });

        Schema::create('citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('chunk_id', 150);
            $table->unsignedInteger('page_number');
            $table->text('snippet');
            $table->decimal('score', 6, 4)->default(0.0000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citations');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
    }
};
