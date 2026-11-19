<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentMetadata extends Model
{
    use HasFactory;

    protected $table = 'document_metadata';

    protected $fillable = [
        'document_id',
        'issuing_organization',
        'reference_number',
        'document_date',
        'project_name',
        'budget',
        'deadline',
        'keywords',
        'summary',
        'summary_generated_at',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'keywords' => 'array',
            'summary' => 'array',
            'summary_generated_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
