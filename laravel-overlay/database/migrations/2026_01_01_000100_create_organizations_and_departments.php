<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('code', 50)->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
        Schema::dropIfExists('organizations');
    }
};
