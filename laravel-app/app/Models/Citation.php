<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Citation extends Model
{
    protected $fillable = ['chat_message_id', 'document_id', 'chunk_id', 'page_number', 'snippet', 'score'];

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function message(): BelongsTo { return $this->belongsTo(ChatMessage::class, 'chat_message_id'); }
}
