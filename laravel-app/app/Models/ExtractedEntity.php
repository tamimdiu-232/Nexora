<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractedEntity extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'type',
        'value',
        'page_number',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
