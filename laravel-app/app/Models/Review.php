<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    public const STATUSES = ['pending', 'verified', 'rejected', 'needs_correction'];

    protected $fillable = ['chat_message_id', 'requested_by', 'reviewer_id', 'status', 'comment', 'decided_at'];
    protected $casts = ['decided_at' => 'datetime'];

    public function message(): BelongsTo { return $this->belongsTo(ChatMessage::class, 'chat_message_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}
