<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'organization_id' => Organization::factory(),
            'department_id' => null,
            'title' => 'Synthetic Document ' . fake()->unique()->numerify('####'),
            'category' => 'Other',
            'category_source' => 'ai',
            'original_name' => 'sample.pdf',
            'stored_path' => 'documents/test/' . Str::uuid() . '.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1000,
            'sha256' => hash('sha256', Str::random(10)),
            'page_count' => 1,
            'processing_status' => 'completed',
            'processed_at' => now(),
        ];
    }
}
