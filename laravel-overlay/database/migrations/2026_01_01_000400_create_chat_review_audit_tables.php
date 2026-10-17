<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title')->default('New chat');
            $t->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $t->string('role', 10); // user | assistant
            $t->longText('content');
            $t->string('status', 30)->nullable(); // answered | insufficient_evidence | conflicting_evidence
            $t->string('generated_by', 60)->nullable();
            $t->timestamps();
        });

        Schema::create('citations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->string('chunk_id', 150);
            $t->unsignedInteger('page_number');
            $t->text('snippet')->nullable();
            $t->float('score')->nullable();
            $t->timestamps();
        });

        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('pending')->index(); // pending|verified|rejected|needs_correction
            $t->text('comment')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('organization_id')->nullable()->index();
            $t->string('action', 80)->index();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'reviews', 'citations', 'chat_messages', 'chat_sessions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
