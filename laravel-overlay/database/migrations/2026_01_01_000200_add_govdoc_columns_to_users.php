<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->string('role', 30)->default('viewer')->index();
            $t->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('organization_id');
            $t->dropConstrainedForeignId('department_id');
            $t->dropColumn(['role', 'is_active']);
        });
    }
};
