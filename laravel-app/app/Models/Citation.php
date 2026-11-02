<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Citation extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_message_id',
        'document_id',
        'chunk_id',
        'page_number',
        'snippet',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'score' => 'float',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
