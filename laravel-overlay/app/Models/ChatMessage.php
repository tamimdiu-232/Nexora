<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatMessage extends Model
{
    protected $fillable = ['chat_session_id', 'role', 'content', 'status', 'generated_by'];

    public function session(): BelongsTo { return $this->belongsTo(ChatSession::class, 'chat_session_id'); }
    public function citations(): HasMany { return $this->hasMany(Citation::class); }
    public function review(): HasOne { return $this->hasOne(Review::class)->latestOfMany(); }

    /** The user question that led to this assistant answer. */
    public function question(): ?string
    {
        return static::where('chat_session_id', $this->chat_session_id)->where('role', 'user')
            ->where('id', '<', $this->id)->orderByDesc('id')->value('content');
    }
}
