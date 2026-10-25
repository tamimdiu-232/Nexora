<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentMetadata extends Model
{
    protected $table = 'document_metadata';
    protected $fillable = ['document_id', 'issuing_organization', 'reference_number', 'document_date', 'project_name',
        'budget', 'deadline', 'keywords', 'summary', 'summary_generated_at'];
    protected $casts = ['keywords' => 'array', 'summary' => 'array', 'summary_generated_at' => 'datetime'];

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
}
